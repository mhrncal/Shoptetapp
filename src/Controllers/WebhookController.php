<?php
namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\Webhook;

class WebhookController extends BaseController
{
    public function index(): void
    {
        $userId   = $this->user['id'];
        $webhooks = Webhook::allForUser($userId);

        // Přidáme posledních 5 logů ke každému hooku
        foreach ($webhooks as &$wh) {
            $wh['recent_logs'] = Webhook::getRecentLogs($wh['id'], 5);
        }

        $this->view('webhooks/index', [
            'pageTitle' => 'Webhooky',
            'webhooks'  => $webhooks,
            'allEvents' => Webhook::EVENTS,
        ]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $data   = $this->extractData();
        $errors = $this->validate($data);

        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect('/webhooks');
        }

        Webhook::create($userId, $data);
        Session::flash('success', 'Webhook byl vytvořen.');
        $this->redirect('/webhooks');
    }

    public function update(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];

        if (!Webhook::findById($id, $userId)) Response::notFound();

        $data   = $this->extractData();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect('/webhooks');
        }

        Webhook::update($id, $userId, $data);
        Session::flash('success', 'Webhook byl uložen.');
        $this->redirect('/webhooks');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $id  = (int)$this->request->param('id');
        $ok  = Webhook::delete($id, $this->user['id']);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Webhook smazán.' : 'Webhook nenalezen.');
        $this->redirect('/webhooks');
    }

    private function extractData(): array
    {
        $events = $this->request->post('events', []);
        // Filtr jen povolených eventů
        $events = is_array($events)
            ? array_values(array_intersect($events, array_keys(Webhook::EVENTS)))
            : [];

        return [
            'name'        => trim($this->request->post('name', '')),
            'url'         => trim($this->request->post('url', '')),
            'events'      => $events,
            'is_active'   => $this->request->post('is_active'),
            'retry_count' => max(1, min(5, (int)$this->request->post('retry_count', 3))),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['name'])) $errors[] = 'Zadejte název webhooku.';
        if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Zadejte platnou URL adresu.';
        }
        if (empty($data['events'])) $errors[] = 'Vyberte alespoň jeden event.';
        return $errors;
    }
}
