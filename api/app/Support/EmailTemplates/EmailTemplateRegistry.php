<?php

namespace App\Support\EmailTemplates;

/**
 * Every email template the app sends. Register templates in App\Providers\EmailTemplateServiceProvider.
 */
class EmailTemplateRegistry
{
    /**
     * @var array<string, EmailTemplate>
     */
    protected array $templates = [];

    public function register(EmailTemplate $template): self
    {
        $this->templates[$template->key] = $template;

        return $this;
    }

    /**
     * @return array<string, EmailTemplate>
     */
    public function all(): array
    {
        return $this->templates;
    }

    public function find(string $key): ?EmailTemplate
    {
        return $this->templates[$key] ?? null;
    }
}
