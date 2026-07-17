@extends('layouts.demo')
@section('title','Personalización - Demo')
@section('header-title','Personalización')
@section('header-subtitle','Contenido público y branding (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $settings = [
      'branding.name' => 'Clínica Oñate',
      'branding.footer_text' => '© 2026 Clínica Oñate. Todos los derechos reservados.',
      'branding.logo' => 'logo.png',
      'branding.accent' => '#0f766e',
      'branding.accent_strong' => '#14b8a6',
      'branding.accent_soft' => '#ccfbf1',
      'home.hero_badge' => 'Salud y Bienestar',
      'home.hero_title' => 'Clínica Médica Familiar',
      'home.hero_subtitle' => 'Tu salud es nuestra prioridad más alta.',
      'home.hero_primary_text' => 'Agendar Cita',
      'home.hero_secondary_text' => 'Nuestros Servicios',
      'home.hero_stat_1_label' => 'Pacientes Satisfechos',
      'home.hero_stat_1_value' => '10,000+',
      'home.hero_stat_1_note' => 'En toda la región',
      'home.hero_stat_2_label' => 'Médicos Especialistas',
      'home.hero_stat_2_value' => '30+',
      'home.hero_stat_2_note' => 'Certificados',
      'home.hero_stat_3_label' => 'Años de Experiencia',
      'home.hero_stat_3_value' => '15',
      'home.hero_stat_3_note' => 'Trayectoria impecable',
      'home.about_label' => 'Sobre Nosotros',
      'home.about_title' => 'Comprometidos con tu Bienestar',
      'home.about_body' => 'Ofrecemos servicios de salud integral con profesionales altamente calificados.',
      'home.services_label' => 'Especialidades',
      'home.services_title' => 'Nuestras Áreas de Especialización',
  ];
@endphp

@section('main')
<div class="space-y-6">
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form class="form space-y-6" method="POST" action="{{ route('demo.admin.personalizacion.request') }}" enctype="multipart/form-data">
    @csrf
    @include('shared.personalizacion-form', ['settings' => $settings])

    <div class="flex flex-wrap items-center justify-end gap-3">
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection
