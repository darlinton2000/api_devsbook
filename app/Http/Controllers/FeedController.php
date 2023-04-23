<?php

namespace App\Http\Controllers;

use App\Post;
use App\PostComment;
use App\PostLike;
use App\User;
use App\UserRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;

class FeedController extends Controller
{
    private $loggedUser;
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    public function create(Request $request)
    {
        $array = ['error' => ''];
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $type  = $request->input('type');
        $body  = $request->input('body');
        $photo = $request->input('photo');

        if ($type) {

            switch ($type) {
                case 'text':
                    if (!$body) {
                        $array['error'] = 'Texto nao enviado!';
                        return  $array;
                    }
                break;
                case 'photo':
                    if ($photo) {
                        if (in_array($photo->getClientMimeType(), $allowedTypes)) {

                            $filename = md5(time() . rand(0,9999)) . '.jpg';

                            $destPath = public_path('/media/uploads');

                            $img = Image::make($photo->path())
                                ->resize(800, null, function ($constraint){
                                    $constraint->aspectRadio();
                                })
                                ->save($destPath . '/' . $filename);

                            $body = $filename;
                        } else {
                            $array['error'] = 'Arquivo nao suportado';
                            return $array;
                        }
                    } else {
                        $array['error'] = 'Arquivo nao enviado';
                        return $array;
                    }
                break;
                default:
                    $array['error'] = 'Tipo de postagem inexistente';
                    return $array;
                break;
            }

            if ($body) {
                $newPost = new Post();
                $newPost->id_user    = $this->loggedUser['id'];
                $newPost->type       = $type;
                $newPost->created_at = date('Y-m-d H:i:s');
                $newPost->body       = $body;
                $newPost->save();
            }

        } else {
            $array['error'] = 'Dados nao enviados.';
            return $array;
        }

        return $array;
    }

    public function read(Request $request)
    {
        $array = ['error' => ''];

        $page = intval($request->input('page'));
        $perPage = 2;

        // 1. Pegar a lista de usuarios que eu sigo (incluido eu mesmo)
        $users = [];
        $userList = UserRelation::where('user_from', $this->loggedUser['id'])->get();
        foreach ($userList as $userItem) {
            $users[] = $userItem['user_to'];
        }
        $users[] = $this->loggedUser['id'];

        // 2. Pegar os posts dessa galera ordenando pela data
        $postList = Post::whereIn('id_user', $users)
                            ->orderBy('created_at', 'desc')
                            ->offset($page * $perPage)
                            ->limit($perPage)
                            ->get();

        $total = Post::whereIn('id_user', $users)->count();
        $pageCount = ceil($total / $perPage);

        // 3. Preencher as informacoes adicionais
        $posts = $this->_postListToObject($postList, $this->loggedUser['id']);

        $array['posts'] = $posts;
        $array['pageCount'] = $pageCount;
        $array['currentPage'] = $page;

        return $array;
    }

    public function userFeed(Request $request, $id = false)
    {
        $array = ['error' => ''];

        if ($id == false) {
            $id = $this->loggedUser['id'];
        }

        $page = intval($request->input('page'));
        $perPage = 2;

        // 1. Pegar os posts do usuario ordenado pela data
        $postList = Post::where('id_user', $id)
            ->orderBy('created_at', 'desc')
            ->offset($page * $perPage)
            ->limit($perPage)
            ->get();

        $total = Post::where('id_user', $id)->count();
        $pageCount = ceil($total / $perPage);

        // 2. Preencher as informacoes adicionais
        $posts = $this->_postListToObject($postList, $this->loggedUser['id']);

        $array['posts'] = $posts;
        $array['pageCount'] = $pageCount;
        $array['currentPage'] = $page;

        return $array;
    }

    private function _postListToObject($postList, $loggedId)
    {
        foreach ($postList as $postKey => $postItem) {

            // Verificando se o post é meu
            if ($postItem['id_user'] == $loggedId) {
                $postList[$postKey]['mime'] = true;
            } else {
                $postList[$postKey]['mime'] = false;
            }

            // Preencher informacoes de usurio
            $userInfo = User::find($postItem['id_user']);
            $userInfo['avatar'] = url('media/avatars/' . $userInfo['avatar']);
            $userInfo['cover'] = url('media/covers/' . $userInfo['cover']);
            $postList[$postKey]['user'] = $userInfo;

            // Preencher informacoes de like
            $likes = PostLike::where('id_post', $postItem['id'])->count();
            $postList[$postKey]['likeCount'] = $likes;

            $isLiked = PostLike::where('id_post', $postItem['id'])
                                    ->where('id_user', $loggedId)
                                    ->count();
            $postList[$postKey]['liked'] = ($isLiked > 0) ? true : false;

            // Preencher informacoes de Comments
            $comments = PostComment::where('id_post', $postItem['id'])->get();
            foreach ($comments as $commentKey => $comment) {
                $user = User::find($comment['id_user']);
                $user['avatar'] = url('media/avatars/' . $user['avatar']);
                $user['cover'] = url('media/covers/' . $user['cover']);
                $comments[$commentKey]['user'] = $user;
            }

            $postList[$postKey]['comments'] = $comments;

        }

        return $postList;
    }

    public function userPhotos(Request $request, $id = false)
    {
        $array = ['error' => ''];

        if ($id == false) {
            $id = $this->loggedUser['id'];
        }

        $page = intval($request->input('page'));
        $perPage = 2;

        // 1. Pegar as fotos do usuario ordenado pela data
        $postList = Post::where('id_user', $id)
            ->where('type', 'photo')
            ->orderBy('created_at', 'desc')
            ->offset($page * $perPage)
            ->limit($perPage)
            ->get();

        $total = Post::where('id_user', $id)->where('type', 'photo')->count();
        $pageCount = ceil($total / $perPage);

        // 2. Preencher as informacoes adicionais
        $posts = $this->_postListToObject($postList, $this->loggedUser['id']);

        foreach ($posts as $pkey => $post) {
            $posts[$pkey]['body'] = url('media/uploads/'.$posts[$pkey]['body']);
        }

        $array['posts'] = $posts;
        $array['pageCount'] = $pageCount;
        $array['currentPage'] = $page;

        return $array;
    }
}
