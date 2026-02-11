<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = \Spatie\Permission\Models\Permission::all();
        $groupedPermissions = [];
        
        foreach ($permissions as $perm) {
            $parts = explode('_', $perm->name); // e.g. view_users
            $entity = count($parts) > 1 ? end($parts) : 'General';
            $groupedPermissions[$entity][] = $perm;
        }

        return view('admin.roles.create', compact('groupedPermissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado exitosamente.');
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all();
        $groupedPermissions = [];
        
        foreach ($permissions as $perm) {
            $parts = explode('_', $perm->name);
            $entity = count($parts) > 1 ? end($parts) : 'General';
            $groupedPermissions[$entity][] = $perm;
        }

        return view('admin.roles.edit', compact('role', 'groupedPermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($request->permissions);

        return redirect()->route('roles.index')
            ->with('success', 'Permisos actualizados correctamente para el rol ' . $role->name);
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return back()->with('error', 'No puedes eliminar el rol de Administrador.');
        }
        
        $role->delete();
        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }
}
