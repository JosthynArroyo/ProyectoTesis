@extends('layouts.guest')

@section('content')
<div class="max-w-lg mx-auto py-10 space-y-6">
    <h1 class="text-2xl font-bold text-center">Ingresar con reconocimiento facial</h1>

    <form id="faceLoginForm" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium">Correo</label>
            <input type="email" name="email" class="mt-1 w-full border rounded px-3 py-2" required>
        </div>

        <video id="video" autoplay class="w-full rounded shadow"></video>

        <button class="w-full bg-indigo-600 text-white py-2 rounded" id="loginBtn" type="submit">Entrar</button>
        <a href="{{ route('login') }}" class="block text-center text-sm text-gray-500">Usar correo y contraseña</a>
    </form>

    <div id="error" class="text-red-600 text-center"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js" defer></script>
<script type="module">
    import { initFaceApi, captureDescriptor, sendDescriptor } from "{{ mix('js/face-auth.js') }}";

    document.addEventListener('DOMContentLoaded', async () => {
        await initFaceApi();
        const video = document.getElementById('video');
        await navigator.mediaDevices.getUserMedia({ video: true }).then(stream => video.srcObject = stream);

        document.getElementById('faceLoginForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            const descriptor = await captureDescriptor(video);
            formData.append('descriptor', JSON.stringify(descriptor));
            try {
                const response = await fetch('{{ route('face.login') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: formData
                });
                if (!response.ok) throw await response.json();
                const data = await response.json();
                window.location.href = data.redirect;
            } catch (error) {
                document.getElementById('error').textContent = error.message ?? 'No fue posible validar el rostro.';
            }
        });
    });
</script>
@endsection
