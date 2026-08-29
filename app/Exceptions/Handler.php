<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Exceptions\InvalidOrderTransitionException;
// OJO: la de Symfony, que es la que lanza abort(). Estaba importada la de
// php-http/httplug, asi que `instanceof HttpException` nunca se cumplia y
// toda la rama de abajo (incluidas las vistas 500/503 del ecommerce)
// estuvo muerta: cualquier abort() terminaba en el catch-all que
// responde 500. Un tenant bloqueado (abort 403) mostraba
// "Error del servidor" con status 500.
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Response;
use Throwable;


class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];


    /**
     * Report or log an exception.
     *
     * @param  Throwable $exception
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }


    /**
    * Render an exception into an HTTP response.
    *
    * @param \Illuminate\Http\Request $request
    * @param Throwable $exception
    * @return \Symfony\Component\HttpFoundation\Response
    *
    * @throws Throwable
    */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof InvalidOrderTransitionException) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }
            return redirect()->back()->with('error', $exception->getMessage());
        }

        if ($exception instanceof AuthenticationException)
        {
            if ($this->isFrontend($request))
            {
                return redirect()->guest('login');
            }

            return $this->errorResponse('No se encuentra autenticado', 401, $exception);
        }


        if($exception instanceof AuthorizationException)
        {
            return $this->errorResponse('No posee permisos para ejecutar esta acción', 403, $exception);
        }


        if ($exception instanceof ValidationException)
        {
            return $this->convertValidationExceptionToResponse($exception, $request);
        }


        // ModelNotFoundException (findOrFail) debe tratarse como 404, no 500
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            if ($request->is('ecommerce*') && !$request->expectsJson()) {
                try {
                    return response(view('ecommerce::errors.404'), 404);
                } catch (\Throwable $e) {}
            }
            return $this->errorResponse('No se encontró el recurso solicitado', 404, $exception);
        }

        if($exception instanceof NotFoundHttpException)
        {
            // Mostrar página 404 del ecommerce cuando la URL comienza con /ecommerce
            if ($request->is('ecommerce*') && !$request->expectsJson()) {
                try {
                    return response(view('ecommerce::errors.404'), 404);
                } catch (\Throwable $e) {}
            }
            return $this->errorResponse('No se encontró la URL especificada', 404, $exception);
        }


        if($exception instanceof MethodNotAllowedHttpException)
        {
            return $this->errorResponse('El método especificado en la petición no es válido', 405, $exception);
        }


        if (preg_match('/InventoryTrait.php/', $exception->getFile()))
        {
            return $this->errorResponse($exception->getMessage(), 405, $exception);
        }


        if($exception instanceof HttpException)
        {
            $statusCode = $exception->getStatusCode();

            if ($request->is('ecommerce*') && !$request->expectsJson()) {
                // match(true) y no match($statusCode): asi un 502 o un 504
                // tambien caen en la pagina de error en vez de escaparse.
                $ecView = match(true) {
                    $statusCode === 503 => 'ecommerce::errors.503',
                    $statusCode === 404 => 'ecommerce::errors.404',
                    $statusCode >= 500  => 'ecommerce::errors.500',
                    default             => null,
                };
                if ($ecView) {
                    try {
                        return response(view($ecView), $statusCode);
                    } catch (\Throwable $e) {}
                }
            }

            if ($request->expectsJson()) {
                return $this->errorResponse('', $statusCode, $exception);
            }

            // Sin vista propia, la pagina de error estandar de Laravel. Antes
            // devolvia JSON con el codigo de la excepcion (normalmente 0), que
            // no es un status HTTP valido.
            return parent::render($request, $exception);
        }


        if(!$this->isFrontend($request))
        {
            return $this->errorResponse('', 500, $exception);
        }

        // Capturar errores 500 no manejados en rutas ecommerce
        if ($request->is('ecommerce*') && !$request->expectsJson()) {
            try {
                return response(view('ecommerce::errors.500'), 500);
            } catch (\Throwable $e) {}
        }

        return parent::render($request, $exception);
    }


    /**
     *
     * @param  ValidationException $e
     * @param  $request
     * @return Response
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, $request)
    {
        $errors = $e->validator->errors()->getMessages();

        if ($this->isFrontend($request))
        {
            return $request->ajax()? response()->json($errors, 422):redirect()
                ->back()
                ->withInput($request->input())
                ->withErrors($errors);
        }

        return $this->errorResponse($errors, 422, $e);
    }

    private function isFrontend(Request $request)
    {
        return $request->acceptsHtml() && $request->route() && collect($request->route()->middleware())->contains('web');
    }


    private function errorResponse($message, $code, $exception)
    {
        $message = ($message === '')?$exception->getMessage():$message;
        $code = ($code === '')?$exception->getCode():$code;

        // getCode() de una excepcion no es un status HTTP: suele ser 0, y
        // response()->json(..., 0) revienta. Ante cualquier valor fuera de
        // rango se responde 500.
        $code = (is_numeric($code) && $code >= 100 && $code <= 599) ? (int) $code : 500;

        $response = ['success' => false, 'message' => $message];

        if (config('app.debug')) {
            $response['file'] = $exception->getFile();
            $response['line'] = $exception->getLine();
        }

        return response()->json($response, $code);
    }
}