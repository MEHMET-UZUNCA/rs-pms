<?php

namespace App\Http\Middleware;

use App\Domain\Core\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Web API: "Authorization: Bearer <anahtar>" — anahtarın SHA-256 özeti pms_api_clients'ta aranır; tesis anahtardan belirlenir. */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $client = $token !== '' ? DB::table('pms_api_clients')->where('token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;
        if (! $client) {
            return response()->json(['error' => 'unauthorized', 'message' => 'Missing or invalid API key.'], 401);
        }
        DB::table('pms_api_clients')->where('id', $client->id)->update(['last_used_at' => now()]);
        app()->instance(Property::class, Property::query()->findOrFail($client->property_id));
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
