<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $users = [];
        if ($user && $user->isAdmin()) {
            $users = User::orderBy('name')->get();
        }

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $auth = Auth::user();

        if (!$auth || !$auth->isAdmin()) {
            abort(403);
        }

        $rules = [
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'level' => ['required', Rule::in(['user', 'admin'])],

            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'avatar' => ['nullable', 'image', 'max:2048'],
        ];

        $messages = [
            'name.required' => 'Informe o nome do usuário.',
            'email.required' => 'Informe o e-mail do usuário.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'level.required' => 'Informe o nível.',
            'level.in' => 'Nível inválido.',
            'password.required' => 'Informe a senha.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'avatar.image' => 'A foto precisa ser uma imagem (JPG/PNG/WebP).',
            'avatar.max' => 'A foto deve ter no máximo 2MB.',
        ];

        $data = $request->validate($rules, $messages);

        $newUser = new User();
        $newUser->name = $data['name'];
        $newUser->email = $data['email'];
        $newUser->level = $data['level'];
        $newUser->password = Hash::make($data['password']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $newUser->avatar_path = $path;
        }

        $newUser->save();

        return back()->with('success', 'Usuário criado com sucesso.');
    }

    public function updateUser(Request $request, User $user)
    {
        $auth = Auth::user();
        abort_unless($auth && $auth->isAdmin(), 403);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'level' => ['required', Rule::in(['user', 'admin'])],

            // senha opcional
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],

            // avatar opcional
            'avatar' => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'name.required' => 'Informe o nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'level.required' => 'Informe o nível.',
            'level.in' => 'Nível inválido.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'avatar.mimes' => 'A foto deve ser JPG, PNG ou WebP.',
            'avatar.max' => 'A foto deve ter no máximo 2MB.',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->level = $data['level'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return back()->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        $auth = Auth::user();
        abort_unless($auth && $auth->isAdmin(), 403);

        // não excluir a si mesmo
        if ($auth->id === $user->id) {
            return back()->withErrors(['user' => 'Você não pode excluir seu próprio usuário.']);
        }

        // não permitir remover o último admin
        $isTargetAdmin = ($user->level ?? ($user->level ?? 'user')) === 'admin';
        if ($isTargetAdmin) {
            $adminsCount = User::where('level', 'admin')
                ->orWhere('level', 'admin') // caso use role em algum lugar
                ->count();

            if ($adminsCount <= 1) {
                return back()->withErrors(['user' => 'Não é possível excluir o último administrador do sistema.']);
            }
        }

        // remove avatar, se existir
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->delete();

        return back()->with('success', 'Usuário excluído com sucesso.');
    }
}
