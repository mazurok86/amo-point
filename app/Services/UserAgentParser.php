<?php

namespace App\Services;

use Jenssegers\Agent\Agent;

class UserAgentParser
{
    /**
     * @return array{device: string, browser: ?string, os: ?string}
     */
    public function parse(string $userAgent): array
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        return [
            'device' => $this->resolveDevice($agent),
            'browser' => $agent->browser() ?: null,
            'os' => $agent->platform() ?: null,
        ];
    }

    private function resolveDevice(Agent $agent): string
    {
        return match (true) {
            $agent->isRobot() => 'bot',
            $agent->isTablet() => 'tablet',
            $agent->isMobile() => 'mobile',
            default => 'desktop',
        };
    }
}
