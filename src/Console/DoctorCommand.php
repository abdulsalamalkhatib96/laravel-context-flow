<?php

namespace Abdulsalam\LaravelContextFlow\Console;

use Abdulsalam\LaravelContextFlow\Context\ContextRegistry;
use Abdulsalam\LaravelContextFlow\Security\SensitiveKeyDetector;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

final class DoctorCommand extends Command
{
    protected $signature = 'context-flow:doctor';
    protected $description = 'Validate Laravel Context Flow configuration for common security and propagation mistakes';

    public function handle(Config $config, ContextRegistry $registry, SensitiveKeyDetector $sensitive): int
    {
        $errors = 0;
        $warnings = 0;

        $this->line('<info>✓</info> Laravel Context Flow package loaded');

        $trusted = (array) $config->get('context-flow.http.trusted_hosts', []);
        if ($trusted === []) {
            $this->line('<comment>⚠</comment> No trusted internal HTTP hosts are configured.');
            ++$warnings;
        } else {
            $this->line('<info>✓</info> '.count($trusted).' trusted host pattern(s) configured');
        }

        $signInternal = (bool) $config->get('context-flow.security.sign_internal_context', false);
        $signingKey = (string) $config->get('context-flow.security.signing_key', '');
        if ($signInternal && strlen($signingKey) < 32) {
            $this->line('<error>✗</error> Internal signing is enabled but CONTEXT_FLOW_SIGNING_KEY is missing or shorter than 32 bytes.');
            ++$errors;
        } elseif ($signInternal) {
            $this->line('<info>✓</info> Internal context signing is enabled');
        } else {
            $this->line('<comment>⚠</comment> Internal context signing is disabled; restricted inbound baggage will not be trusted.');
            ++$warnings;
        }

        foreach ($registry->all() as $definition) {
            if ($sensitive->isSensitive($definition->name) && $definition->targets !== []) {
                $this->line('<error>✗</error> Sensitive-looking key ['.$definition->name.'] is configured for propagation.');
                ++$errors;
            }

            if ($definition->accepts(\Abdulsalam\LaravelContextFlow\Enums\TrustLevel::Public)
                && in_array($definition->name, ['tenant_id', 'actor_id', 'role', 'permissions'], true)) {
                $this->line('<error>✗</error> Authorization-related key ['.$definition->name.'] accepts public inbound values.');
                ++$errors;
            }
        }

        $maxBytes = (int) $config->get('context-flow.limits.http_total_bytes', 4096);
        if ($maxBytes > 8192) {
            $this->line('<comment>⚠</comment> HTTP context size limit exceeds 8 KiB; proxies may reject large headers.');
            ++$warnings;
        } else {
            $this->line('<info>✓</info> HTTP propagation size is bounded to '.$maxBytes.' bytes');
        }

        $this->newLine();
        $this->line("Result: {$errors} error(s), {$warnings} warning(s)");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
