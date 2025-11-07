@extends('layouts.doctor')
@section('title', 'Panel Doctor - Clínica Los Ángeles')
@section('activeSidebar', 'dashboard')

@push('head')
    <meta name="doctor-dashboard-data" content="{{ route('doctor.dashboard.data') }}">
    <meta name="user-id" content="{{ Auth::id() }}">
@endpush

@push('scripts')
    @vite(['resources/js/dashboard-doctor.js'])
@endpush

@section('content')
    <h1>Panel del Doctor</h1>
    <div class="date">
        <input type="date" value="{{ now()->format('Y-m-d') }}">
    </div>

    @if(session('success'))
        <div class="alert success">{{ session('success') }}</div>
    @endif

    <div class="insights">
        <div class="sales">
            <span class="material-symbols-sharp">calendar_month</span>
            <div class="middle">
                <div class="left">
                    <h3>Mis Citas Hoy</h3>
                    <h1 id="k-hoy">{{ $citasHoy }}</h1>
                </div>
                <div class="progress">
                    <svg>
                        <circle r="30" cx="40" cy="40"></circle>
                    </svg>
                    <div class="number"></div>
                </div>
            </div>
            <small>Hoy</small>
        </div>
        <div class="expenses">
            <span class="material-symbols-sharp">check_circle</span>
            <div class="middle">
                <div class="left">
                    <h3>Atendidas</h3>
                    <h1 id="k-realizadas">{{ $citasRealizadas }}</h1>
                </div>
                <div class="progress">
                    <svg>
                        <circle r="30" cx="40" cy="40"></circle>
                    </svg>
                    <div class="number"></div>
                </div>
            </div>
            <small>Hoy</small>
        </div>
        <div class="income">
            <span class="material-symbols-sharp">pending_actions</span>
            <div class="middle">
                <div class="left">
                    <h3>Pendientes</h3>
                    <h1 id="k-pendientes">{{ $citasPendientes }}</h1>
                </div>
                <div class="progress">
                    <svg>
                        <circle r="30" cx="40" cy="40"></circle>
                    </svg>
                    <div class="number"></div>
                </div>
            </div>
            <small>Hoy</small>
        </div>
    </div>

    <div class="recent_order">
        <h1>Citas Recientes</h1>
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                </tr>
            </thead>
            <tbody id="tbody-citas">
                @forelse($citas as $c)
                    <tr>
                        <td>{{ $c->paciente->name ?? 'Paciente' }}</td>
                        <td class="{{ $c->estado === 'pendiente' ? 'warning' : ($c->estado === 'realizada' ? 'success' : ($c->estado === 'confirmada' ? 'info' : 'danger')) }}">
                            {{ ucfirst($c->estado) }}
                        </td>
                        <td>{{ \Illuminate\Support\Carbon::parse($c->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $c->hora }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Sin citas para hoy.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@section('right')
    <div class="recent_updates">
        <h2>Actividad Reciente</h2>
        <div class="updates" id="updates"></div>
    </div>
    <div class="sales_analytics">
        <h2>Resumen de Citas</h2>
        <div class="item online">
            <div class="icon">
                <span class="material-symbols-sharp">calendar_month</span>
            </div>
            <div class="right_text">
                <div class="info">
                    <h3>Confirmadas</h3>
                    <small class="text-muted"></small>
                </div>
                <h3 id="k-conf-2h">{{ $citasConfirmadas2h }}</h3>
            </div>
        </div>
        <div class="item online">
            <div class="icon">
                <span class="material-symbols-sharp">task_alt</span>
            </div>
            <div class="right_text">
                <div class="info">
                    <h3>Atendidas</h3>
                    <small class="text-muted"></small>
                </div>
                <h3 id="k-real-2h">{{ $citasRealizadas2h }}</h3>
            </div>
        </div>
        <div class="item online">
            <div class="icon">
                <span class="material-symbols-sharp">cancel</span>
            </div>
            <div class="right_text">
                <div class="info">
                    <h3>Canceladas</h3>
                    <small class="text-muted"></small>
                </div>
                <h3 id="k-canc-2h">{{ $citasCanceladas2h }}</h3>
            </div>
        </div>
    </div>
@endsection
