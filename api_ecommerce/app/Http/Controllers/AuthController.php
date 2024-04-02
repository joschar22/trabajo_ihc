<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Mail\verifiedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Validator;

class AuthController extends Controller
{

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'login_ecommerce','verified_auth']]);
    }


    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required',
            'surname' => 'required',
            'dni' => 'required',
            'direccion' => 'required',
            'telefono' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = new User;
        $user->name = request()->name;
        $user->surname = request()->surname;
        $user->dni = request()->dni;
        $user->direccion = request()->direccion;
        $user->telefono = request()->telefono;
        $user->email = request()->email;
        $user->tipoUsuario = 2;
        $user->uniqd = uniqid();
        $user->password = bcrypt(request()->password);
        $user->save();

        Mail::to(request()->email)->send(new verifiedMail($user));

        return response()->json($user, 201);
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (!$token = auth('api')->attempt([
            'email' => request()->email,
            'password' => request()->password,
            'tipoUsuario' => 1
        ])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function login_ecommerce()
    {
        $credentials = request(['email', 'password']);

        if (!$token = auth('api')->attempt([
            'email' => request()->email,
            'password' => request()->password,
            'tipoUsuario' => 2
        ])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!auth('api')->user()->email_verified_at) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->respondWithToken($token);
    }

    public function verified_auth(Request $request)
    {
        $user = User::where('uniqd', $request->code_user)->first();

        if ($user) {
            $user->update(['email_verified_at' => now()]);
            return response()->json(['meassage' => 200]);
        }
        return response()->json(['meassage' => 403]);
    }
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'full_name' => auth('api')->user()->name . " " . auth('api')->user()->surname,
                'email' => auth('api')->user()->email,
            ]
        ]);
    }
}
