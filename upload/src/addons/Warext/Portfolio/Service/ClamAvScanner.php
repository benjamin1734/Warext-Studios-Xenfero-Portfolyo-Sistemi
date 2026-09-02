<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Exception\ScanUnavailableException;

class ClamAvScanner extends AbstractService
{
    public function scan(string $localPath): array
    {
        if (!is_file($localPath))
        {
            throw new ScanUnavailableException('scan_file_missing');
        }

        $mode = strtolower((string)($this->app->options()->wrxtPortfolioClamAvMode ?? 'unix'));
        $connectTimeout = max(1, min(15, (int)($this->app->options()->wrxtPortfolioClamAvConnectTimeout ?? 3)));
        $readTimeout = max(5, min(120, (int)($this->app->options()->wrxtPortfolioClamAvReadTimeout ?? 30)));

        if ($mode === 'tcp')
        {
            $host = trim((string)($this->app->options()->wrxtPortfolioClamAvHost ?? '127.0.0.1'));
            $port = max(1, min(65535, (int)($this->app->options()->wrxtPortfolioClamAvPort ?? 3310)));
            if (!in_array($host, ['127.0.0.1', 'localhost'], true))
            {
                throw new ScanUnavailableException('clamav_remote_tcp_forbidden');
            }
            $endpoint = 'tcp://' . $host . ':' . $port;
        }
        else
        {
            $socket = trim((string)($this->app->options()->wrxtPortfolioClamAvSocket ?? '/run/clamav/clamd.ctl'));
            if ($socket === '' || $socket[0] !== '/')
            {
                throw new ScanUnavailableException('clamav_socket_invalid');
            }
            $endpoint = 'unix://' . $socket;
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($endpoint, $errno, $errstr, $connectTimeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket))
        {
            throw new ScanUnavailableException('clamav_unavailable');
        }

        stream_set_timeout($socket, $readTimeout);
        $file = fopen($localPath, 'rb');
        if (!is_resource($file))
        {
            fclose($socket);
            throw new ScanUnavailableException('clamav_file_open_failed');
        }

        try
        {
            $this->writeAll($socket, "zINSTREAM\0");
            while (!feof($file))
            {
                $chunk = fread($file, 1024 * 1024);
                if ($chunk === false)
                {
                    throw new ScanUnavailableException('clamav_stream_read_failed');
                }
                if ($chunk === '')
                {
                    break;
                }
                $this->writeAll($socket, pack('N', strlen($chunk)) . $chunk);
            }
            $this->writeAll($socket, pack('N', 0));

            $reply = '';
            while (!feof($socket) && strlen($reply) < 8192)
            {
                $part = fread($socket, 4096);
                if ($part === false)
                {
                    break;
                }
                $reply .= $part;
                if (str_contains($reply, "\0"))
                {
                    break;
                }
            }
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out']))
            {
                throw new ScanUnavailableException('clamav_timeout');
            }
        }
        finally
        {
            fclose($file);
            fclose($socket);
        }

        $reply = trim(str_replace("\0", '', $reply));
        if ($reply === '')
        {
            throw new ScanUnavailableException('clamav_empty_reply');
        }
        if (preg_match('/:\s+OK$/', $reply))
        {
            return ['status' => 'clean', 'signature' => '', 'reply' => $reply];
        }
        if (preg_match('/:\s+(.+)\s+FOUND$/', $reply, $match))
        {
            return ['status' => 'infected', 'signature' => mb_substr(trim($match[1]), 0, 255), 'reply' => $reply];
        }
        if (str_contains($reply, 'ERROR') || str_contains($reply, 'size limit exceeded'))
        {
            throw new ScanUnavailableException('clamav_scan_error');
        }

        throw new ScanUnavailableException('clamav_unknown_reply');
    }

    public function ping(): bool
    {
        try
        {
            $mode = strtolower((string)($this->app->options()->wrxtPortfolioClamAvMode ?? 'unix'));
            if ($mode === 'tcp')
            {
                $host = trim((string)($this->app->options()->wrxtPortfolioClamAvHost ?? '127.0.0.1'));
                $port = max(1, min(65535, (int)($this->app->options()->wrxtPortfolioClamAvPort ?? 3310)));
                if (!in_array($host, ['127.0.0.1', 'localhost'], true))
                {
                    return false;
                }
                $endpoint = 'tcp://' . $host . ':' . $port;
            }
            else
            {
                $socketPath = trim((string)($this->app->options()->wrxtPortfolioClamAvSocket ?? '/run/clamav/clamd.ctl'));
                if ($socketPath === '' || $socketPath[0] !== '/')
                {
                    return false;
                }
                $endpoint = 'unix://' . $socketPath;
            }

            $socket = @stream_socket_client($endpoint, $errno, $errstr, 2, STREAM_CLIENT_CONNECT);
            if (!is_resource($socket))
            {
                return false;
            }
            stream_set_timeout($socket, 3);
            $this->writeAll($socket, "zPING\0");
            $reply = fread($socket, 64);
            fclose($socket);
            return is_string($reply) && str_contains($reply, 'PONG');
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    private function writeAll($stream, string $data): void
    {
        $length = strlen($data);
        $offset = 0;
        while ($offset < $length)
        {
            $written = fwrite($stream, substr($data, $offset));
            if ($written === false || $written === 0)
            {
                throw new ScanUnavailableException('clamav_write_failed');
            }
            $offset += $written;
        }
    }
}
