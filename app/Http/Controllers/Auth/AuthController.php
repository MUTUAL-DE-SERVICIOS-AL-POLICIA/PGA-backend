<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\Models\Employee;
use App\Helpers\Ldap;
use App\Models\Fund;
use App\Models\UserStore;

class AuthController extends Controller
{
    /**
     * Método de autenticación principal
     */
    public function login(AuthForm $request)
    {

        $lastFund = Fund::orderBy('id', 'desc')->first();

        if ($lastFund && $lastFund->username_responsible === $request['username']) {

            $nameParts = explode(' ', $lastFund->name_responsible);
            if (count($nameParts) >= 3) {
                $firstName = $nameParts[0];
                $lastName = $nameParts[1];
                $mothersLastName = $nameParts[2];
                $employee = Employee::where('first_name', $firstName)
                    ->where(function ($query) use ($lastName, $mothersLastName) {
                        $query->where('last_name', $lastName)
                            ->orWhere('mothers_last_name', $mothersLastName);
                    })
                    ->first();

                if ($employee) {
                    return $this->respondWithFundToken($employee->createToken('api')->plainTextToken, $employee->id, $request['username']);
                }
            }
        }

        $user = User::whereUsername($request['username'])->first();
        if (!$user) {
            return $this->unauthorizedResponse('Usuario no encontrado');
        }

        if (!$user->active) {
            return $this->unauthorizedResponse('Usuario desactivado');
        }
        if (!env("LDAP_AUTHENTICATION")) {
            logger("1");
            return $this->handleDatabaseAuthentication($request, $user);
        } else {
            logger("2");
            return $this->handleLdapAuthentication($request);
        }
    }

    private function handleDatabaseAuthentication($request, $user)
    {
        if (Hash::check($request['password'], $user->password)) {
            return $this->respondWithToken($user->createToken('api')->plainTextToken, $user);
        } else {
            return $this->unauthorizedResponse('Contraseña incorrecta');
        }
    }

    private function handleLdapAuthentication($request)
    {
        $ldap = new Ldap();
        if ($ldap->connection && $ldap->verify_open_port()) {
            if ($ldap->bind($request['username'], $request['password'])) {
                $user = User::where('username', $request['username'])->where('active', true)->first();
                if ($user) {
                    if (!Hash::check($request['password'], $user->password)) {
                        $user->password = Hash::make($request['password']);
                        $user->save();
                    }
                    $token = $user->createToken('api')->plainTextToken;

                    return $this->respondWithToken($token, $user);
                } else {
                    return $this->unauthorizedResponse('Usuario o contraseña incorrectos');
                }
            } else {
                return $this->unauthorizedResponse('Usuario o contraseña incorrectos');
            }
        } else {
            return $this->serverErrorResponse('No se pudo conectar con el servidor LDAP');
        }
    }

    protected function respondWithToken($token, $user)
    {
        $id = $user->employee_id;
        $username = $user->username;
        $role = $user->roles[0]->name;
        $permissions = array_unique(array_merge(
            $user->roles[0]->permissions->pluck('name')->toArray(),
            $user->permissions->pluck('name')->toArray()
        ));

        $usersActives = UserStore::where('active', true)->get();
        foreach ($usersActives as $activeUser) {
            if ($username === $activeUser->name_user) {
                $permissions[] = 'create-employee';
                break;
            }
        }
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? now()->addMinutes(config('sanctum.expiration'))->timestamp : null,
            'id' => $id,
            'user' => $username,
            'role' => $role,
            'permissions' => $permissions,
            'message' => 'Identidad verificada',
        ], 200);
    }
    private function unauthorizedResponse($message)
    {
        return response()->json([
            'message' => 'No autorizado',
            'errors' => [
                'type' => [$message],
            ],
        ], 401);
    }

    /**
     * Respuesta de error de servidor
     */
    private function serverErrorResponse($message)
    {
        return response()->json([
            'message' => $message,
            'errors' => [
                'type' => ['Error de conexión'],
            ],
        ], 500);
    }

    private function respondWithFundToken($token, $id, $username)
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? now()->addMinutes(config('sanctum.expiration'))->timestamp : null,
            'id' => $id,
            'user' => $username,
            'role' => 'Encargado de Caja Chica',
            'permissions' => ['operation_pettycash'],
            'message' => 'Autenticación directa para Encargado de caja chica',
        ], 200);
    }
}
