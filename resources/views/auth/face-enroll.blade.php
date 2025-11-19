@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto py-8">
    <h1 class="text-2xl font-bold mb-4">Registrar reconocimiento facial</h1>

    @if (session('status'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('status') }}</div>
    @endif

    <div class="space-y-6">
        <video id="video" autoplay class="w-full rounded shadow"></video>
        <button id="capture" class="bg-indigo-600 text-white px-4 py-2 rounded">Guardar perfil</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js" defer></script>
<script type="module">
    import { initFaceApi, captureDescriptor, sendDescriptor } from "{{ mix('js/face-auth.js') }}";

    document.addEventListener('DOMContentLoaded', async () => {
        await initFaceApi();
        const video = document.getElementById('video');
        await navigator.mediaDevices.getUserMedia({ video: true }).then(stream => video.srcObject = stream);

        document.getElementById('capture').addEventListener('click', async () => {
            const descriptor = await captureDescriptor(video);
            await sendDescriptor('{{ route('face.enroll') }}', descriptor, '{{ csrf_token() }}');
            window.location.reload();
        });
    });
</script>
@endsection
