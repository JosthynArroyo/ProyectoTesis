<?php

namespace App\Services\Chatbot;

use App\Models\Dependiente;
use App\Models\User;
use App\Support\ChatbotSessionKeys;
use App\Support\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatbotProfileService
{
    public function perfil(Request $request): array
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'perfil' => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'dni' => $user->dni,
                    'direccion' => $user->direccion,
                    'fecha_nacimiento' => $user->fecha_nacimiento ? $user->fecha_nacimiento->toDateString() : null,
                    'sexo' => $user->sexo,
                ],
                'sexos' => ['Masculino', 'Femenino', 'Otro'],
                'dependientes' => Dependiente::where('user_id', $user->id)->activos()->orderBy('nombre')->get()->map(fn ($d) => [
                    'id' => $d->id,
                    'nombre' => $d->nombre,
                    'nombre_completo' => $d->nombreConParentesco(),
                    'dni' => $d->dni,
                    'fecha_nacimiento' => $d->fecha_nacimiento->toDateString(),
                    'sexo' => $d->sexo,
                    'parentesco' => $d->parentesco,
                    'telefono_emergencia' => $d->telefono_emergencia,
                    'notas' => $d->notas,
                ]),
            ],
        ];
    }

    public function actualizarPerfil(Request $request): array
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        if ($request->filled('dependiente_id')) {
            $dep = Dependiente::where('id', $request->input('dependiente_id'))->where('user_id', $user->id)->first();
            if (! $dep) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'El dependiente seleccionado no es válido.',
                    ],
                ];
            }
            if (! $dep->activo) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'El dependiente seleccionado está inactivo.',
                    ],
                ];
            }

            $rules = ValidationRules::dependiente(true, $dep->id);
            $validated = $request->validate($rules);

            $dep->update([
                'nombre' => $validated['nombre'],
                'dni' => $validated['dni'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'sexo' => $validated['sexo'] ?? null,
                'parentesco' => $validated['parentesco'],
                'telefono_emergencia' => $validated['telefono_emergencia'] ?? null,
                'notas' => $validated['notas'] ?? null,
            ]);

            return [
                'status' => 200,
                'payload' => [
                    'ok' => true,
                    'message' => 'Datos del dependiente actualizados correctamente.',
                ],
            ];
        }

        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ValidationRules::telefono(),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', Rule::in(['Masculino', 'Femenino', 'Otro'])],
        ];

        $messages = [
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos.',
        ];

        $validated = $request->validate($rules, $messages);

        $user->update([
            'name' => $validated['nombre'],
            'telefono' => $validated['telefono'],
            'direccion' => $validated['direccion'],
            'fecha_nacimiento' => $validated['fecha_nacimiento'],
            'sexo' => $validated['sexo'],
        ]);

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Datos actualizados correctamente.',
                'perfil' => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'dni' => $user->dni,
                    'direccion' => $user->direccion,
                    'fecha_nacimiento' => $user->fecha_nacimiento ? $user->fecha_nacimiento->toDateString() : null,
                    'sexo' => $user->sexo,
                ],
            ],
        ];
    }
}
