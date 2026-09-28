<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioRequest;
use App\Models\Audit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::query()
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('username', 'like', "%$t%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')->paginate(20)->withQueryString();

        return $this->tableOrPage($request, 'admin.usuarios.index', 'admin.usuarios._table', compact('usuarios'));
    }

    public function create(): View
    {
        return view('admin.usuarios.form', ['usuario' => new User(['active' => true])]);
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        $usuario = User::create($request->validated());

        return $this->ok("Usuario {$usuario->username} creado.");
    }

    public function show(User $usuario): View
    {
        $actividad = Audit::with('auditable')->where('user_id', $usuario->id)->latest('id')->limit(30)->get();

        return view('admin.usuarios.show', compact('usuario', 'actividad'));
    }

    public function edit(User $usuario): View
    {
        return view('admin.usuarios.form', compact('usuario'));
    }

    public function update(UsuarioRequest $request, User $usuario): JsonResponse
    {
        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        abort_if($usuario->is(auth()->user()) && empty($data['active']), 422, 'No puedes desactivar tu propio usuario.');
        $usuario->update($data);

        return $this->ok("Usuario {$usuario->username} actualizado.");
    }

    public function destroy(User $usuario): JsonResponse
    {
        abort_if($usuario->is(auth()->user()), 422, 'No puedes eliminar tu propio usuario.');
        $usuario->delete();

        return $this->ok('Usuario eliminado.');
    }
}
