<?php

namespace App\Http\Controllers;

use App\Domain\Users\Services\ContactDeliverySettingsService;
use App\Domain\Admin\Services\SiteAssetsService;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminSettingsController extends Controller
{
    public function index(
        Request $request,
        ContactDeliverySettingsService $contactDeliverySettings,
        SiteAssetsService $siteAssets
    )
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $deliverySettings = $contactDeliverySettings->get();
        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/settings',
        ])['data'];

        return Inertia::render('Admin/Settings', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/settings',
            'breadcrumbs' => $breadcrumbs,
            'contactDelivery' => $deliverySettings,
            'assets' => $siteAssets->get(),
        ]);
    }

    public function update(
        Request $request,
        ContactDeliverySettingsService $contactDeliverySettings
    )
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $data = $request->validate([
            'email_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ], [
            'smtp_port.integer' => 'Порт SMTP должен быть числом.',
            'smtp_port.min' => 'Порт SMTP должен быть больше 0.',
            'smtp_port.max' => 'Порт SMTP должен быть меньше 65536.',
            'smtp_from_address.email' => 'Email отправителя должен быть корректным.',
        ]);

        if (!empty($data['email_enabled'])) {
            $errors = [];
            if (empty($data['smtp_host'])) {
                $errors['smtp_host'] = 'Укажите SMTP host.';
            }
            if (empty($data['smtp_port'])) {
                $errors['smtp_port'] = 'Укажите SMTP порт.';
            }
            if (empty($data['smtp_from_address'])) {
                $errors['smtp_from_address'] = 'Укажите email отправителя.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $contactDeliverySettings->update($data);

        return back()->with('notice', 'Настройки обновлены.');
    }

    public function testEmail(Request $request, ContactDeliverySettingsService $contactDeliverySettings)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
            'test_body' => ['required', 'string', 'max:2000'],
        ], [
            'test_email.required' => 'Укажите email для теста.',
            'test_email.email' => 'Email для теста должен быть корректным.',
            'test_body.required' => 'Введите текст тестового сообщения.',
        ]);

        $settings = $contactDeliverySettings->get();
        $emailSettings = $settings['email'] ?? [];
        if (!($emailSettings['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'test_email' => 'Email-доставка отключена.',
            ]);
        }

        $smtp = $emailSettings['smtp'] ?? [];
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 0);
        $fromAddress = trim((string) ($smtp['from_address'] ?? ''));

        if ($host === '' || $port <= 0 || $fromAddress === '') {
            throw ValidationException::withMessages([
                'test_email' => 'SMTP не настроен полностью.',
            ]);
        }

        $encryption = (string) ($smtp['encryption'] ?? 'tls');
        $scheme = null;
        $autoTls = true;
        if ($encryption === 'none') {
            $autoTls = false;
        } elseif ($encryption === 'ssl') {
            $scheme = 'smtps';
            $autoTls = false;
        }

        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => (string) ($smtp['username'] ?? ''),
            'mail.mailers.smtp.password' => (string) ($smtp['password'] ?? ''),
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.auto_tls' => $autoTls,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => (string) ($smtp['from_name'] ?? config('app.name')),
        ]);

        try {
            Mail::mailer('smtp')->raw($data['test_body'], function ($message) use ($data, $fromAddress, $smtp) {
                $message->to($data['test_email'])
                    ->subject('Тест SMTP')
                    ->from($fromAddress, (string) ($smtp['from_name'] ?? config('app.name')));
            });
        } catch (\Throwable $exception) {
            Log::error('Test email failed.', [
                'error' => $exception->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'test_email' => 'Не удалось отправить тестовое письмо.',
            ]);
        }

        return back()->with('notice', 'Тестовое письмо отправлено.');
    }

    public function uploadAvatarPlaceholder(Request $request, SiteAssetsService $siteAssets)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $data = $request->validate([
            'avatar_placeholder' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'avatar_placeholder.required' => 'Выберите файл заглушки.',
            'avatar_placeholder.mimes' => 'Поддерживаются форматы jpg, jpeg, png, webp.',
            'avatar_placeholder.max' => 'Размер файла не должен превышать 4 МБ.',
        ]);

        $siteAssets->updateAvatarPlaceholder($data['avatar_placeholder']);

        return back()->with('notice', 'Заглушка аватара обновлена.');
    }

    private function getRoleLevel(Request $request): int
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        return (int) $user->roles()->max('level');
    }

    private function ensureAccess(int $roleLevel, int $minLevel): void
    {
        if ($roleLevel <= $minLevel) {
            abort(403);
        }
    }
}
