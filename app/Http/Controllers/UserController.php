<?php

namespace App\Http\Controllers;

use App\Post;
use App\User;
use App\UserRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    /**
     * Faz a atualizacao dos dados do usuario
     * @param Request $request
     * @return string[]
     */
    public function update(Request $request)
    {
        $array = ['error' => ''];

        // Recebendo os dados
        $name             = $request->input('name');
        $email            = $request->input('email');
        $birthdate        = $request->input('birthdate');
        $city             = $request->input('city');
        $work             = $request->input('work');
        $password         = $request->input('password');
        $password_confirm = $request->input('password_confirm');

        $user = User::find($this->loggedUser['id']);

        // Name
        if ($name) {
            $user->name = $name;
        }

        // E-mail
        if ($email) {
            if ($email != $user->email) {
                $emailExist = User::where('email', $email)->count();
                if ($emailExist === 0) {
                    $user->email = $email;
                } else {
                    $array['error'] = 'E-mail ja existe!';
                    return $array;
                }
            }
        }

        // Birthdate
        if ($birthdate) {
            if (strtotime($birthdate) === false) {
                $array['error'] = 'Data de nascimento invalida!';
                return $array;
            }
            $user->birthdate = $birthdate;
        }

        // City
        if ($city) {
            $user->city = $city;
        }

        // Work
        if ($work) {
            $user->work = $work;
        }

        // Password
        if ($password && $password_confirm) {
            if ($password === $password_confirm) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $user->password = $hash;
            } else {
                $array['error'] = 'As senhas nao batem.';
                return $array;
            }
        }

        $user->save();

        return $array;
    }

    /**
     * Faz a atualizacao do avatar do usuario
     * @param Request $request
     * @return string[]
     */
    public function updateAvatar(Request $request)
    {
        $array = ['error' => ''];
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('avatar');

        if ($image) {
            if (in_array($image->getClientMimeType(), $allowedTypes)) {

                $filename = md5(time() . rand(0,9999)) . '.jpg';

                $destPath = public_path('/media/avatars');

                $img = Image::make($image->path())
                        ->fit(200,200)
                        ->save($destPath . '/' . $filename);

                $user = User::find($this->loggedUser['id']);
                $user->avatar = $filename;
                $user->save();

                $array['url'] = url('/media/avatars/' . $filename);
            } else {
                $array['error'] = 'Arquivo nao suportado!';
                return $array;
            }
        } else {
            $array['error'] = 'Arquivo nao enviado!';
            return $array;
        }

        return $array;
    }

    public function updateCover(Request $request)
    {
        $array = ['error' => ''];
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('cover');

        if ($image) {
            if (in_array($image->getClientMimeType(), $allowedTypes)) {

                $filename = md5(time() . rand(0,9999)) . '.jpg';

                $destPath = public_path('/media/covers');

                $img = Image::make($image->path())
                    ->fit(850,310)
                    ->save($destPath . '/' . $filename);

                $user = User::find($this->loggedUser['id']);
                $user->cover = $filename;
                $user->save();

                $array['url'] = url('/media/covers/' . $filename);
            } else {
                $array['error'] = 'Arquivo nao suportado!';
                return $array;
            }
        } else {
            $array['error'] = 'Arquivo nao enviado!';
            return $array;
        }

        return $array;
    }

    /**
     * Pegando informações do usuário
     * @param $id
     * @return string[]
     * @throws \Exception
     */
    public function read($id = false)
    {
        $array = ['error' => ''];

        if ($id) {
            $info = User::find($id);
            if (!$info) {
                $array['error'] = 'Usuario inexistente!';
                return $array;
            }
        } else {
            $info = $this->loggedUser;
        }

        $info['avatar'] = url('media/avatars/'.$info['avatar']);
        $info['cover'] = url('media/covers/'.$info['cover']);

        $info['me'] = ($info['id'] == $this->loggedUser['id']) ? true : false;

        $dateFrom = new \DateTime($info['birthdate']);
        $dateTo = new \DateTime('today');
        $info['age'] = $dateFrom->diff($dateTo)->y;

        $info['followers'] = UserRelation::where('user_to', $info['id'])->count();
        $info['following'] = UserRelation::where('user_from', $info['id'])->count();

        $info['photoCount'] = Post::where('id_user', $info['id'])->where('type', 'photo')->count();

        $hasRelation = UserRelation::where('user_from', $this->loggedUser['id'])->where('user_to', $info['id'])->count();
        $info['isFollowing'] = ($hasRelation > 0) ? true : false;

        $array['data'] = $info;

        return $array;
    }

    public function follow($id)
    {
        $array = ['error' => ''];

        if ($id == $this->loggedUser['id']) {
            $array['error'] = 'Voce nao pode seguir a si mesmo.';
            return $array;
        }

        $userExists = User::find($id);
        if ($userExists) {
            $relation = UserRelation::where('user_from', $this->loggedUser['id'])->where('user_to', $id)->first();

            if ($relation) {
                // parar de seguir
                $relation->delete();
            } else {
                // seguir
                $newRelation = new UserRelation();
                $newRelation->user_from = $this->loggedUser['id'];
                $newRelation->user_to = $id;
                $newRelation->save();
            }
        } else {
            $array['error'] = 'Usuario inexistente!';
            return $array;
        }

        return $array;
    }
}
