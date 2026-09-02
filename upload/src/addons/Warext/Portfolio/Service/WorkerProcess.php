<?php

namespace Warext\Portfolio\Service;

use XF\Service\AbstractService;
use Warext\Portfolio\Exception\ProcessingUnavailableException;
use Warext\Portfolio\Exception\ModelRejectedException;

class WorkerProcess extends AbstractService
{
    public function runImageWorker(string $input, string $displayOutput, string $thumbOutput): array
    {
        if (!function_exists('proc_open') || !function_exists('proc_get_status'))
        {
            throw new ProcessingUnavailableException('worker_proc_open_unavailable');
        }

        $php = $this->resolvePhpBinary();
        if (!$php)
        {
            throw new ProcessingUnavailableException('worker_php_cli_unavailable');
        }

        $worker = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Worker' . DIRECTORY_SEPARATOR . 'ImageReencode.php';
        if (!is_file($worker))
        {
            throw new ProcessingUnavailableException('image_worker_missing');
        }

        $workDir = dirname($displayOutput);
        $memoryMb = max(64, min(1024, (int)$this->app->options()->wrxtPortfolioWorkerMemoryMb));
        $timeout = max(5, min(120, (int)$this->app->options()->wrxtPortfolioWorkerTimeoutSeconds));
        $displayMax = max(512, min(8192, (int)$this->app->options()->wrxtPortfolioDisplayMaxDimension));
        $thumbMax = max(128, min(2048, (int)$this->app->options()->wrxtPortfolioThumbnailMaxDimension));
        $quality = max(50, min(95, (int)$this->app->options()->wrxtPortfolioWebpQuality));

        $disableFunctions = implode(',', [
            'exec', 'passthru', 'shell_exec', 'system', 'proc_open', 'popen', 'pcntl_exec',
            'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server',
            'curl_exec', 'curl_multi_exec'
        ]);
        $openBasedir = $workDir . PATH_SEPARATOR . dirname($worker);

        $command = [
            $php,
            '-d', 'memory_limit=' . $memoryMb . 'M',
            '-d', 'max_execution_time=' . $timeout,
            '-d', 'max_input_time=' . $timeout,
            '-d', 'allow_url_fopen=0',
            '-d', 'allow_url_include=0',
            '-d', 'display_errors=0',
            '-d', 'log_errors=0',
            '-d', 'expose_php=0',
            '-d', 'open_basedir=' . $openBasedir,
            '-d', 'disable_functions=' . $disableFunctions,
            $worker,
            $input,
            $displayOutput,
            $thumbOutput,
            (string)$displayMax,
            (string)$thumbMax,
            (string)$quality
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $env = ['PATH' => '', 'HOME' => '', 'TMPDIR' => $workDir, 'TEMP' => $workDir, 'TMP' => $workDir];
        $process = @proc_open($command, $descriptor, $pipes, $workDir, $env, ['bypass_shell' => true]);
        if (!is_resource($process))
        {
            throw new ProcessingUnavailableException('worker_start_failed');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $started = microtime(true);
        $timedOut = false;
        $lastStatus = null;

        try
        {
            while (true)
            {
                $stdout .= (string)stream_get_contents($pipes[1], 65536);
                $stderr .= (string)stream_get_contents($pipes[2], 65536);
                if (strlen($stdout) > 65536 || strlen($stderr) > 65536)
                {
                    @proc_terminate($process, 9);
                    throw new \RuntimeException('worker_output_limit');
                }

                $lastStatus = proc_get_status($process);
                if (!$lastStatus['running'])
                {
                    break;
                }
                if ((microtime(true) - $started) > $timeout)
                {
                    $timedOut = true;
                    @proc_terminate($process, 15);
                    usleep(150000);
                    $status = proc_get_status($process);
                    if ($status['running'])
                    {
                        @proc_terminate($process, 9);
                    }
                    break;
                }
                usleep(50000);
            }
        }
        finally
        {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }

        $exitCode = proc_close($process);
        if ($timedOut)
        {
            throw new \RuntimeException('worker_timeout');
        }
        if ($exitCode === -1 && is_array($lastStatus) && isset($lastStatus['exitcode']) && $lastStatus['exitcode'] >= 0)
        {
            $exitCode = (int)$lastStatus['exitcode'];
        }
        if ($exitCode !== 0)
        {
            $decoded = json_decode(trim($stdout), true);
            $reason = is_array($decoded) && !empty($decoded['error']) ? (string)$decoded['error'] : 'worker_failed';
            throw new ProcessingUnavailableException(substr($reason, 0, 100));
        }

        $result = json_decode(trim($stdout), true);
        if (!is_array($result) || empty($result['ok']))
        {
            throw new ProcessingUnavailableException('worker_invalid_response');
        }

        return $result;
    }

    public function runGlbWorker(string $input): array
    {
        $config = [
            'vertices' => max(1000, (int)$this->app->options()->wrxtPortfolioMaxModelVertices),
            'triangles' => max(1000, (int)$this->app->options()->wrxtPortfolioMaxModelTriangles),
            'meshes' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelMeshes),
            'primitives' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelPrimitives),
            'nodes' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelNodes),
            'materials' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelMaterials),
            'textures' => max(0, (int)$this->app->options()->wrxtPortfolioMaxModelTextures),
            'animations' => max(0, (int)$this->app->options()->wrxtPortfolioMaxModelAnimations),
            'skins' => max(0, (int)$this->app->options()->wrxtPortfolioMaxModelSkins),
            'joints' => max(0, min(48, (int)$this->app->options()->wrxtPortfolioMaxModelJoints)),
            'accessors' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelAccessors),
            'bufferViews' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelBufferViews),
            'accessorElements' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelAccessorElements),
            'textureDimension' => max(128, (int)$this->app->options()->wrxtPortfolioMaxModelTextureDimension),
            'textureBytes' => max(1024, (int)$this->app->options()->wrxtPortfolioMaxModelTextureBytes),
            'animationKeyframes' => max(1, (int)$this->app->options()->wrxtPortfolioMaxModelAnimationKeyframes),
            'depth' => max(8, min(256, (int)$this->app->options()->wrxtPortfolioMaxModelNodeDepth))
        ];

        $worker = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Worker' . DIRECTORY_SEPARATOR . 'GlbAnalyze.php';
        if (!is_file($worker))
        {
            throw new ProcessingUnavailableException('glb_worker_missing');
        }
        try
        {
            return $this->runCliWorker($worker, [$input, base64_encode(json_encode($config, JSON_UNESCAPED_SLASHES))], dirname($input));
        }
        catch (ProcessingUnavailableException $e)
        {
            if (str_starts_with($e->getMessage(), 'glb_'))
            {
                throw new ModelRejectedException($e->getMessage(), 0, $e);
            }
            throw $e;
        }
    }

    private function runCliWorker(string $worker, array $arguments, string $workDir): array
    {
        if (!function_exists('proc_open') || !function_exists('proc_get_status'))
        {
            throw new ProcessingUnavailableException('worker_proc_open_unavailable');
        }
        $php = $this->resolvePhpBinary();
        if (!$php)
        {
            throw new ProcessingUnavailableException('worker_php_cli_unavailable');
        }
        $memoryMb = max(64, min(1024, (int)$this->app->options()->wrxtPortfolioWorkerMemoryMb));
        $timeout = max(5, min(120, (int)$this->app->options()->wrxtPortfolioWorkerTimeoutSeconds));
        $disableFunctions = implode(',', [
            'exec','passthru','shell_exec','system','proc_open','popen','pcntl_exec',
            'fsockopen','pfsockopen','stream_socket_client','stream_socket_server','curl_exec','curl_multi_exec'
        ]);
        $openBasedir = $workDir . PATH_SEPARATOR . dirname($worker);
        $command = [
            $php, '-d', 'memory_limit=' . $memoryMb . 'M', '-d', 'max_execution_time=' . $timeout,
            '-d', 'max_input_time=' . $timeout, '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
            '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'expose_php=0',
            '-d', 'open_basedir=' . $openBasedir, '-d', 'disable_functions=' . $disableFunctions, $worker, ...$arguments
        ];
        $descriptor = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $env = ['PATH'=>'','HOME'=>'','TMPDIR'=>$workDir,'TEMP'=>$workDir,'TMP'=>$workDir];
        $process = @proc_open($command, $descriptor, $pipes, $workDir, $env, ['bypass_shell' => true]);
        if (!is_resource($process))
        {
            throw new ProcessingUnavailableException('worker_start_failed');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout=''; $stderr=''; $started=microtime(true); $timedOut=false; $lastStatus=null;
        try
        {
            while (true)
            {
                $stdout .= (string)stream_get_contents($pipes[1], 65536);
                $stderr .= (string)stream_get_contents($pipes[2], 65536);
                if (strlen($stdout) > 131072 || strlen($stderr) > 65536)
                {
                    @proc_terminate($process, 9);
                    throw new \RuntimeException('worker_output_limit');
                }
                $lastStatus = proc_get_status($process);
                if (!$lastStatus['running']) break;
                if ((microtime(true)-$started) > $timeout)
                {
                    $timedOut=true; @proc_terminate($process,15); usleep(150000);
                    $status=proc_get_status($process); if ($status['running']) @proc_terminate($process,9); break;
                }
                usleep(50000);
            }
        }
        finally
        {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
        }
        $exitCode = proc_close($process);
        if ($timedOut) throw new \RuntimeException('worker_timeout');
        if ($exitCode === -1 && is_array($lastStatus) && isset($lastStatus['exitcode']) && $lastStatus['exitcode'] >= 0) $exitCode=(int)$lastStatus['exitcode'];
        $result=json_decode(trim($stdout), true);
        if ($exitCode !== 0 || !is_array($result) || empty($result['ok']))
        {
            $reason=is_array($result) && !empty($result['error']) ? (string)$result['error'] : 'worker_failed';
            throw new ProcessingUnavailableException(substr($reason,0,100));
        }
        return $result;
    }

    private function resolvePhpBinary(): ?string
    {
        $configured = trim((string)$this->app->options()->wrxtPortfolioWorkerPhpPath);
        $candidates = array_filter([
            $configured,
            '/opt/cpanel/ea-php84/root/usr/bin/php',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            PHP_SAPI === 'cli' ? PHP_BINARY : null
        ]);
        foreach (array_unique($candidates) as $candidate)
        {
            if (is_file($candidate) && is_executable($candidate))
            {
                return $candidate;
            }
        }
        return null;
    }
}
