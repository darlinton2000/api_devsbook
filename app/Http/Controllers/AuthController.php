<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\JsonResponse;
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
     * Metodo para retornar algo quando nao for autorizado
     * @return JsonResponse
     */
    public function unauthorized()
    {
        return response()->json(['error' => 'Nao autorizado'], 401);
    }

    /**
     * Faz o login na API
     * @param Request $request
     * @return string[]
     */
    public function login(Request $request)
    {
        $array = ['error' => ''];

        // Recebendo os dados
        $email = $request->input('email');
        $password = $request->input('password');

        if ($email && $password) {
            $token = auth()->attempt([
                'email'    => $email,
                'password' => $password
            ]);

            if (!$token) {
                $array['error'] = 'E-mail e/ou senha errados';
                return $array;
            }

            $array['token'] = $token;
            return $array;
        }

        $array['error'] = 'Dados não enviados!';
        return $array;
    }

    /**
     * Faz o logout na API
     * @return string[]
     */
    public function logout()
    {
        auth()->logout();
        return ['error' => ''];
    }

    /**
     * Atualiza o token
     * @return array
     */
    public function refresh()
    {
        $token = auth()->refresh();
        return [
            'error' => '',
            'token' => $token
        ];
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
