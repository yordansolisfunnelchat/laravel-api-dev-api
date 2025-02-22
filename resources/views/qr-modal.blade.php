<div
    x-data="{
        qrCode: '',
        loading: true,
        connected: false,
        disconnected: false,
        pollQrCode() {
            fetch(`/evolution/connect/${@js($instance->id)}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = true;
                    setTimeout(() => {
                        this.updateQrCode();
                        setInterval(() => {
                            this.updateQrCode();
                        }, 5000);
                    }, 5000);
                });
        },
        updateQrCode() {
            fetch(`/instances/${@js($instance->id)}/qr-code`)
                .then(response => response.json())
                .then(data => {
                    this.qrCode = data.qr_code;
                    this.loading = false;
                });
        },
        checkConnectionStatus() {
            setInterval(() => {
                fetch(`/instances/${@js($instance->id)}/status`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'connected') {
                            this.connected = true;
                            this.loading = false;
                            // Recargar la página
                            location.reload();
                            // O cerrar la modal
                            // document.getElementById('qrModal').close();
                        } else if (data.status === 'disconnected') {
                            this.disconnected = true;
                            this.loading = false;
                            this.pollQrCode(); // Permitir escanear de nuevo el código QR
                        }
                    });
            }, 5000);
        }
    }"
    x-init="pollQrCode(); checkConnectionStatus();"
>
    <div class="text-center">
        <template x-if="loading">
            <p>Cargando QR...</p>
        </template>
        <template x-if="!loading && !connected && !disconnected">
            <img x-bind:src="qrCode" alt="QR Code" class="mx-auto mb-4" style="width: 400px; height: 400px;">
        </template>
        <template x-if="connected">
            <p class="text-green-500">WhatsApp Conectado</p>
        </template>
        <template x-if="disconnected">
            <p class="text-red-500">WhatsApp Desconectado. Escanea el código QR de nuevo.</p>
            <button @click="pollQrCode" class="btn btn-primary">Escanear de nuevo</button>
        </template>
    </div>
</div>