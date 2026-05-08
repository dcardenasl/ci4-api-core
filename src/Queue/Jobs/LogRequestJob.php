<?php

namespace dcardenasl\Ci4ApiCore\Queue\Jobs;

use dcardenasl\Ci4ApiCore\Queue\Job;
use Config\Database;

class LogRequestJob extends Job
{
    /**
     * Handle the job
     *
     * @return void
     */
    public function handle(): void
    {
        $db = Database::connect();

        $logData = [
            'method' => $this->data['method'] ?? 'UNKNOWN',
            'uri' => $this->data['uri'] ?? '',
            'user_id' => $this->data['user_id'] ?? null,
            'ip_address' => $this->data['ip_address'] ?? '',
            'user_agent' => $this->data['user_agent'] ?? '',
            'response_code' => $this->data['response_code'] ?? 0,
            'response_time' => $this->data['response_time'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $db->table('request_logs')->insert($logData);
    }
}
