<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\EventDefaultsService;
use App\Domain\Users\Services\ContactDeliverySettingsService;
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
        EventDefaultsService $defaultsService,
        ContactDeliverySettingsService $contactDeliverySettings
    )
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $defaults = $defaultsService->get();
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
            'defaults' => $defaults,
            'contactDelivery' => $deliverySettings,
        ]);
    }

    public function update(
        Request $request,
        EventDefaultsService $defaultsService,
        ContactDeliverySettingsService $contactDeliverySettings
    )
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $data = $request->validate([
            'lead_time_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'min_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'email_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ], [
            'lead_time_minutes.required' => 'Укажите допустимое время до начала события.',
            'lead_time_minutes.integer' => 'Допустимое время до начала события должно быть числом.',
            'lead_time_minutes.min' => 'Допустимое время до начала события не может быть отрицательным.',
            'lead_time_minutes.max' => 'Допустимое время до начала события не может превышать 1440 минут.',
            'min_duration_minutes.required' => 'Укажите минимальную длительность события.',
            'min_duration_minutes.integer' => 'Минимальная длительность события должна быть числом.',
            'min_duration_minutes.min' => 'Минимальная длительность события не может быть меньше 1 минуты.',
            'min_duration_minutes.max' => 'Минимальная длительность события не может превышать 1440 минут.',
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

        $defaultsService->update($data);
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
        $encryption = $encryption === 'none' ? null : $encryption;

        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => (string) ($smtp['username'] ?? ''),
            'mail.mailers.smtp.password' => (string) ($smtp['password'] ?? ''),
            'mail.mailers.smtp.encryption' => $encryption,
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
