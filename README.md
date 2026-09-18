# mailer-symfony

> **Deprecated.** Use [`symfony/puffer-post-mailer`](https://github.com/symfony/symfony) instead.
> It is maintained in the Symfony monorepo, registers the same `pufferpost+api://` DSN, and now
> carries the template support this package was written for.
>
> Both packages register the same DSN scheme, so installing them together leaves the winner to
> package order. Remove this one:
>
> ```bash
> composer remove pufferpost/symfony-mailer
> composer require symfony/puffer-post-mailer
> ```
>
> Templated sending moves from `MailerEmail` to headers on a plain `Email`:
>
> ```php
> $email->getHeaders()->addTextHeader('X-PufferPost-Template-Id', 'tpl_welcome');
> $email->getHeaders()->addTextHeader('X-PufferPost-Data', json_encode(['name' => 'Jane']));
> ```
>
> Batch sending and workflow triggers stay in [`pufferpost/sdk`](https://github.com/pufferpost/sdk).
