<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitRequest;
use App\Jobs\ResolveVisitGeo;
use App\Models\Visit;
use App\Services\UserAgentParser;
use Illuminate\Http\Response;

class VisitController extends Controller
{
    public function store(StoreVisitRequest $request, UserAgentParser $parser): Response
    {
        $userAgent = (string) ($request->input('user_agent') ?: $request->userAgent() ?: '');
        $parsed = $parser->parse($userAgent);

        $pageUrl = (string) $request->input('page_url');
        $host = (string) (parse_url($pageUrl, PHP_URL_HOST) ?: '');

        $ip = (string) $request->ip();
        $visitorUid = $request->input('visitor_uid')
            ?: md5($ip.'|'.$userAgent.'|'.now()->format('Y-m-d-H'));

        $visit = Visit::create([
            'host' => $host,
            'visitor_uid' => $visitorUid,
            'ip' => $ip,
            'device' => $parsed['device'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'page_url' => $pageUrl,
            'referrer' => $request->input('referrer'),
        ]);

        ResolveVisitGeo::dispatch($visit->id);

        return response()->noContent();
    }
}
