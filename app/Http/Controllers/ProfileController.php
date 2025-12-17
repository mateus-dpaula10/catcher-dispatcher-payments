<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile.index');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        // validate base
        $rules = [
            'name'  => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            // senha opcional
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],

            // foto opcional
            'avatar' => ['nullable', 'image', 'max:2048'], // 2MB
        ];

        // nível só admin pode alterar
        if ($user->isAdmin()) {
            $rules['level'] = ['required', Rule::in(['user', 'admin'])];
        } else {
            // se vier do form, ignora (user readonly)
            $request->merge(['level' => $user->level ?? 'user']);
        }

        $messages = [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'avatar.image' => 'A foto precisa ser uma imagem (JPG/PNG/WebP).',
            'avatar.max' => 'A foto deve ter no máximo 2MB.',
            'level.in' => 'Nível inválido.',
        ];

        $data = $request->validate($rules, $messages);

        // atualiza básicos
        $user->name  = $data['name'];
        $user->email = $data['email'];

        if ($user->isAdmin() && isset($data['level'])) {
            $user->level = $data['level'];
        }

        // senha (se enviada)
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        // avatar (se enviado)
        if ($request->hasFile('avatar')) {
            // apaga a antiga
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $path;
        }

        $user->save();

        return back()->with('success', 'Perfil atualizado com sucesso.');
    }
}
