<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'login',
                'create',
                'unauthorized'
            ]
        ]);
    }

    /**
     * Cria o usuario no BD
     * @param Request $request
     * @return string[]
     */
    public function create(Request $request)
    {
        $array = ['error' => ''];

        // Recebendo os dados
        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $birthdate = $request->input('birthdate');

        if ($name && $email && $password && $birthdate) {
            // Validando a data de nascimento
            if (strtotime($birthdate) === false) {
                $array['error'] = 'Data de nascimento invalida!';
                return $array;
            }

            // Verificar a existencia do e-mail
            $emailExist = User::where('email', $email)->count();
            if ($emailExist === 0) {
                // Criando o hash da senha
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Inserindo no bd
                $newUser = new User();
                $newUser->name = $name;
                $newUser->email = $email;
                $newUser->password = $hash;
                $newUser->birthdate = $birthdate;
                $newUser->save();

                $token = auth()->attempt([
                    'email'    => $email,
                    'password' => $password
                ]);
                if (!$token) {
                    $array['error'] = 'Ocorreu um erro!';
                    return $array;
                }

                $array['token'] = $token;
            } else {
                $array['error'] = 'E-mail ja cadastrado!';
                return $array;
            }
        } else {
            $array['error'] = 'Nao enviou todos os campos.';
            return $array;
        }

        return $array;
    }
}
