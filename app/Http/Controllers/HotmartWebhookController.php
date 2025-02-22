<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Instance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserWelcomeEmail;
use App\Mail\SubscriptionCancelledEmail;
use App\Services\EvolutionApiService;
use Carbon\Carbon;
use App\Mail\DelayedPaymentEmail;

class HotmartWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Hotmart Webhook received', $request->all());

        $event = $request->input('event');
        $data = $request->input('data', []);

        switch ($event) {
            case 'PURCHASE_APPROVED':
                return $this->handlePurchaseApproved($data);
            case 'SUBSCRIPTION_CANCELLATION':
                return $this->handleSubscriptionCancellation($data);
            case 'PURCHASE_DELAYED':
                return $this->handlePurchaseDelayed($data);
            default:
                return response()->json(['message' => 'Unhandled event']);
        }
    }

    private function handlePurchaseApproved($data)
    {
        $buyer = $data['buyer'] ?? [];
        $email = $buyer['email'] ?? null;
        $recurrenceNumber = $data['purchase']['recurrence_number'] ?? 1;

        if (!$email) {
            Log::error('Missing required user information', ['email' => $email]);
            return response()->json(['message' => 'Missing required user information'], 400);
        }

        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Es una nueva suscripción
                $user = $this->createNewUser($buyer);
                $this->sendWelcomeNotifications($user);
            } else {
                // Es una renovación de suscripción
                $this->handleSubscriptionRenewal($user);
            }

            Log::info('Purchase approved processed', ['user_id' => $user->id, 'recurrence_number' => $recurrenceNumber]);

            return response()->json(['message' => 'Purchase approved processed successfully']);
        } catch (\Exception $e) {
            Log::error('Error processing purchase approval', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error processing purchase'], 500);
        }
    }

    private function createNewUser($buyer)
    {
        $user = User::create([
            'name' => $buyer['name'] ?? '',
            'email' => $buyer['email'],
            'phone' => $buyer['checkout_phone'] ?? '',
            'password' => Hash::make('ezcala123'), // Considera usar una contraseña aleatoria
        ]);

        // Aquí puedes añadir lógica adicional para configurar la cuenta del usuario

        return $user;
    }

    private function sendWelcomeNotifications($user)
    {
        $this->sendWhatsAppMessage($user->phone, $user->name, $user->email);
        Mail::to($user->email)->send(new UserWelcomeEmail($user->name, $user->email));
    }

    private function handleSubscriptionRenewal($user)
    {
        // Aquí puedes añadir lógica para manejar la renovación de la suscripción
        // Por ejemplo, actualizar la fecha de vencimiento de la suscripción
    }

    private function handlePurchaseDelayed($data)
    {
        $email = $data['buyer']['email'] ?? null;
        $name = $data['buyer']['name'] ?? '';
        $phone = $data['buyer']['checkout_phone'] ?? null;

        if (!$email) {
            Log::error('Missing required information for delayed purchase', ['email' => $email]);
            return response()->json(['message' => 'Missing required information'], 400);
        }

        try {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->payment_status = 'delayed';
                $user->payment_due_date = Carbon::now()->addHours(24);
                $user->save();

                $this->sendDelayedPaymentNotifications($user, $name, $phone);

                Log::info('User marked as delayed payment and notifications sent', [
                    'user_id' => $user->id, 
                    'due_date' => $user->payment_due_date->toDateTimeString(),
                ]);
            } else {
                Log::warning('User not found for delayed purchase', ['email' => $email]);
            }

            return response()->json(['message' => 'Purchase delay processed successfully']);
        } catch (\Exception $e) {
            Log::error('Error processing purchase delay', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error processing purchase delay'], 500);
        }
    }

    private function sendDelayedPaymentNotifications($user, $name, $phone)
    {
        // Enviar correo electrónico
        Mail::to($user->email)->send(new DelayedPaymentEmail($name));

        // Enviar mensaje de WhatsApp
        $this->sendDelayedPaymentWhatsAppMessage($phone, $name);
    }

    private function sendDelayedPaymentWhatsAppMessage($phone, $name)
    {
        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendText/e_ai_1';
        $apiKey = $evolutionConfig['api_key'];

        $text = "⚠️ Importante.\n\n";
        $text .= "Hola {$name}, intentamos hacer el cobro de tu membresía, pero no ha sido posible.\n\n";
        $text .= "Queremos ayudarte a ponerte al día para que tu servicio no se pause en las próximas 24 horas.\n\n";
        $text .= "Estamos pendientes! 😊";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'apikey' => $apiKey,
        ])->post($serverUrl, [
            'number' => $phone,
            'text' => $text,
            'delay' => 100,
        ]);

        if ($response->successful()) {
            Log::info('WhatsApp delayed payment message sent successfully', ['phone' => $phone]);
        } else {
            Log::error('Failed to send WhatsApp delayed payment message', ['phone' => $phone, 'response' => $response->body()]);
        }
    }


    private function handleSubscriptionCancellation($data)
    {
        $email = $data['subscriber']['email'] ?? null;

        if (!$email) {
            Log::error('Missing subscriber email in cancellation event', ['data' => $data]);
            return response()->json(['message' => 'Missing subscriber email'], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            Log::error('User not found for cancellation event', ['email' => $email]);
            return response()->json(['message' => 'User not found'], 404);
        }

        $instance = Instance::where('user_id', $user->id)->first();

        if (!$instance) {
            Log::error('Instance not found for user', ['user_id' => $user->id]);
            return response()->json(['message' => 'Instance not found'], 404);
        }

        $evolutionApiService = app(EvolutionApiService::class);
        $disconnected = $evolutionApiService->disconnectAndDeactivateInstance($instance);

        if ($disconnected) {
            Mail::to($user->email)->send(new SubscriptionCancelledEmail($user->name));
            $this->sendWhatsAppCancellationMessage($user->phone, $user->name);

            Log::info('Subscription cancelled, instance deactivated, and notifications sent', ['user_id' => $user->id]);
            return response()->json(['message' => 'Subscription cancelled and notifications sent']);
        } else {
            Log::error('Failed to disconnect and deactivate instance', ['instance_id' => $instance->id]);
            return response()->json(['message' => 'Failed to deactivate instance'], 500);
        }
    }

    private function getEvolutionConfig()
    {
        $baseUrl = DB::table('configurations')->where('key', 'evolution_api_base_url')->value('value');
        $apiKey = DB::table('configurations')->where('key', 'evolution_api_key')->value('value');

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey
        ];
    }

    private function sendWhatsAppMessage($phone, $name, $email)
    {
        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendText/e_ai_1';
        $apiKey = $evolutionConfig['api_key'];

        $text = "Bienvenido $name a Ezcala AI 🤖\n\nEstamos super emocionados de tenerte aquí.\n\nAquí tienes los datos para ingresar:\n\nLink: https://ai.ezcala.cloud\nUsuario: $email\nClave: ezcala123\n\nCualquier duda, aquí estamos...";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'apikey' => $apiKey,
        ])->post($serverUrl, [
            'number' => $phone,
            'text' => $text,
            'delay' => 100,
        ]);

        if ($response->successful()) {
            Log::info('WhatsApp message sent successfully', ['phone' => $phone]);
        } else {
            Log::error('Failed to send WhatsApp message', ['phone' => $phone, 'response' => $response->body()]);
        }
    }

    private function sendWhatsAppCancellationMessage($phone, $name)
    {
        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendText/e_ai_1';
        $apiKey = $evolutionConfig['api_key'];

        $text = "Hola $name, lamentamos informarte que tu suscripción a Ezcala AI ha sido cancelada. Si tienes alguna pregunta o deseas reactivar tu cuenta, por favor contáctanos.";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'apikey' => $apiKey,
        ])->post($serverUrl, [
            'number' => $phone,
            'text' => $text,
            'delay' => 100,
        ]);

        if ($response->successful()) {
            Log::info('WhatsApp cancellation message sent successfully', ['phone' => $phone]);
        } else {
            Log::error('Failed to send WhatsApp cancellation message', ['phone' => $phone, 'response' => $response->body()]);
        }
    }
}
