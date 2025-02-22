<div>
    <div class="chat-container">
        <div class="conversation" id="conversation-{{ $conversation->id }}">
            <h3>Conversation ID: {{ $conversation->id }}</h3>
            <div class="messages" id="messages-container">
                @foreach($conversation->messages as $message)
                    <div class="message {{ $message->sender === 'customer' ? 'customer-message' : 'agent-message' }}">
                        @if($message->sender === 'agent')
                            <span class="robot-icon">🤖</span>
                        @endif
                        @if($message->type === 'text')
                            <p>{{ $message->content }}</p>
                        @elseif($message->type === 'image')
                            <img src="{{ $message->content }}" alt="Image" style="max-width: 100%;">
                        @elseif($message->type === 'video')
                            <video controls style="max-width: 100%;">
                                <source src="{{ $message->content }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        @elseif($message->type === 'audio')
                            <audio controls style="max-width: 100%;">
                                <source src="{{ $message->content }}" type="audio/mpeg">
                                Your browser does not support the audio element.
                            </audio>
                        @endif
                        <span class="timestamp">{{ $message->created_at->format('H:i') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .chat-container {
            display: flex;
            flex-direction: column;
            max-width: 600px;
            margin: 20px auto;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            height: 80vh; /* Ajusta la altura según tus necesidades */
            overflow-y: auto; /* Habilita el desplazamiento vertical */
        }
        .conversation {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .messages {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow-y: auto; /* Habilita el desplazamiento vertical */
        }
        .message {
            padding: 8px;
            border-radius: 5px;
            margin: 5px 0;
            max-width: 80%;
            position: relative;
        }
        .customer-message {
            align-self: flex-start;
            background-color: #dcf8c6;
        }
        .agent-message {
            align-self: flex-end;
            background-color: #f1f0f0;
        }
        .robot-icon {
            position: absolute;
            top: 50%;
            left: -25px;
            transform: translateY(-50%);
            font-size: 1.5em;
        }
        .timestamp {
            font-size: 0.8em;
            color: #888;
        }
    </style>
</div>