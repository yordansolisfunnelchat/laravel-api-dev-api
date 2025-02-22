<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{ qrCode: null, status: '{{ $getRecord()->status }}' }" x-init="
        function refreshQR() {
            if (status !== 'connected') {
                fetch('{{ route('get-qr-code', ['instance' => $getRecord()->id]) }}')
                    .then(response => response.json())
                    .then(data => {
                        qrCode = data.qrCode;
                        status = data.status;
                        if (status !== 'connected') {
                            setTimeout(refreshQR, 5000);
                        }
                    });
            }
        }
        refreshQR();
    ">
        <div x-show="status === 'connecting'">
            <div x-show="qrCode">
                <img :src="qrCode" alt="QR Code">
                <p>Scan this QR code with your WhatsApp app to connect</p>
            </div>
            <div x-show="!qrCode">
                Generating QR Code...
            </div>
        </div>
        <div x-show="status === 'connected'">
            Your WhatsApp instance is connected!
        </div>
        <div x-show="status === 'disconnected'">
            Click 'Connect' to start the connection process.
        </div>
    </div>
</x-dynamic-component>