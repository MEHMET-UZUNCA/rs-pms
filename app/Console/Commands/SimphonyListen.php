<?php

namespace App\Console\Commands;

use App\Domain\Pos\Simphony\FrameBuffer;
use App\Domain\Pos\Simphony\MessageHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Simphony PMS arayüzü dinleyicisi (MF_ENH). Simphony istemci, RS PMS sunucudur: EMC'deki arayüz kaydının TCP Host/Port'u bu
 * dinleyiciyi gösterir. Supervisor altında sürekli çalışır: php artisan pms:simphony-listen --interface=1
 * Yalnız izin verilen IP'lerden (allowed_ips) gelen bağlantı kabul edilir.
 */
class SimphonyListen extends Command
{
    protected $signature = 'pms:simphony-listen {--interface= : pms_pos_interfaces kaydı (boşsa ilk etkin Simphony arayüzü)} {--port= : Portu geçici olarak değiştir}';

    protected $description = 'Simphony PMS arayüzü (MF_ENH) için TCP dinleyici';

    public function handle(MessageHandler $handler): int
    {
        $interface = DB::table('pms_pos_interfaces')->where('system', 'simphony')->where('is_active', true)
            ->when($this->option('interface'), fn ($q, $id) => $q->where('id', $id))->orderBy('id')->first();
        if (! $interface) {
            $this->error('Etkin Simphony arayüzü yok (pms_pos_interfaces).');

            return self::FAILURE;
        }
        $address = 'tcp://'.$interface->listen_host.':'.($this->option('port') ?: $interface->listen_port);
        $server = @stream_socket_server($address, $errno, $error);
        if (! $server) {
            $this->error("{$address} açılamadı: {$error}");

            return self::FAILURE;
        }
        stream_set_blocking($server, false);
        $allowed = json_decode((string) $interface->allowed_ips, true) ?: [];
        $this->info("Simphony dinleyici {$address} ({$interface->name})".($allowed ? ' — izinli: '.implode(', ', $allowed) : ''));

        $clients = [];
        $buffers = [];
        $running = true;
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$running) {
                $running = false;
            });
            pcntl_signal(SIGINT, function () use (&$running) {
                $running = false;
            });
        }

        while ($running) {
            $read = [$server, ...array_values($clients)];
            $write = $except = null;
            if (@stream_select($read, $write, $except, 1) === false) {
                continue;
            }
            foreach ($read as $stream) {
                if ($stream === $server) {
                    $client = @stream_socket_accept($server, 0, $peer);
                    if (! $client) {
                        continue;
                    }
                    $ip = explode(':', (string) $peer)[0];
                    if ($allowed && ! in_array($ip, $allowed, true)) {
                        $this->warn("Reddedildi: {$peer}");
                        fclose($client);

                        continue;
                    }
                    stream_set_blocking($client, false);
                    $id = (int) $client;
                    $clients[$id] = $client;
                    $buffers[$id] = new FrameBuffer;
                    $this->line("Bağlandı: {$peer}");

                    continue;
                }
                $id = (int) $stream;
                $chunk = fread($stream, 65536);
                if ($chunk === '' || $chunk === false) {
                    if (feof($stream)) {
                        fclose($stream);
                        unset($clients[$id], $buffers[$id]);
                        $this->line('Bağlantı kapandı.');
                    }

                    continue;
                }
                foreach ($buffers[$id]->push($chunk) as $frame) {
                    $reply = $handler->handleRaw((int) $interface->id, $frame);
                    if ($reply !== '') {
                        fwrite($stream, $reply);
                    }
                    if ($this->output->isVerbose()) {
                        $this->line('← '.self::visible($frame));
                        $this->line('→ '.self::visible($reply));
                    }
                }
            }
            DB::disconnect();   // uzun süre açık bağlantı kalmasın; her mesajda yeniden bağlanır
        }

        foreach ($clients as $client) {
            fclose($client);
        }
        fclose($server);

        return self::SUCCESS;
    }

    private static function visible(string $raw): string
    {
        return strtr($raw, ["\x01" => '<SOH>', "\x02" => '<STX>', "\x03" => '<ETX>', "\x04" => '<EOT>', "\x1c" => '<FS>', "\n" => '[0A]']);
    }
}
