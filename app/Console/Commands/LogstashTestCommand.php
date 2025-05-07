<?php

namespace App\Console\Commands;

use App\Helpers\LogHelper;
use Illuminate\Console\Command;

class LogstashTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:test {message?} {--level=info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un mensaje de prueba a Logstash';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $message = $this->argument('message') ?? 'Prueba de log a Logstash ' . time();
        $level = $this->option('level');

        $this->info("Enviando mensaje a Logstash: '{$message}'");

        $result = LogHelper::toLogstash($message, [
            'test_id' => time(),
            'command' => 'log:test',
            'source' => 'artisan',
        ], $level);

        if ($result) {
            $this->info('Mensaje enviado correctamente a Logstash');
        } else {
            $this->error('Error al enviar mensaje a Logstash');
        }

        return $result ? Command::SUCCESS : Command::FAILURE;
    }
}
