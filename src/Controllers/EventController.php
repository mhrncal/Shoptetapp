<?php
namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\Event;

class EventController extends BaseController
{
    public function index(): void
    {
        $userId = $this->user['id'];
        $tab    = $this->request->get('tab', 'upcoming'); // upcoming | past | all
        $search = $this->request->get('search', '');

        $filters = array_filter(['search' => $search]);
        if ($tab === 'upcoming') $filters['upcoming'] = true;
        if ($tab === 'past')     $filters['past']     = true;

        $events = Event::allForUser($userId, $filters);

        $this->view('events/index', [
            'pageTitle' => 'Kalendář akcí',
            'events'    => $events,
            'tab'       => $tab,
            'search'    => $search,
            'upcoming'  => Event::countUpcoming($userId),
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
            $this->redirect('/events');
        }

        Event::create($userId, $data);
        Session::flash('success', 'Událost byla přidána.');
        $this->redirect('/events');
    }

    public function update(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $event  = Event::findById($id, $userId);
        if (!$event) Response::notFound();

        $data   = $this->extractData();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect('/events');
        }

        Event::update($id, $userId, $data);
        Session::flash('success', 'Událost byla uložena.');
        $this->redirect('/events');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $ok     = Event::delete($id, $userId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Událost smazána.' : 'Událost nenalezena.');
        $this->redirect('/events');
    }

    private function extractData(): array
    {
        // Převedeme datetime-local (YYYY-MM-DDTHH:MM) na MySQL formát
        $toSql = fn($v) => $v ? str_replace('T', ' ', $v) . ':00' : null;

        return [
            'title'          => trim($this->request->post('title', '')),
            'description'    => trim($this->request->post('description', '')),
            'start_date'     => $toSql($this->request->post('start_date')),
            'end_date'       => $toSql($this->request->post('end_date')),
            'event_url'      => trim($this->request->post('event_url', '')),
            'image_url'      => trim($this->request->post('image_url', '')),
            'address'        => trim($this->request->post('address', '')),
            'google_maps_url'=> trim($this->request->post('google_maps_url', '')),
            'is_active'      => $this->request->post('is_active'),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['title']))      $errors[] = 'Název události je povinný.';
        if (empty($data['start_date'])) $errors[] = 'Datum začátku je povinné.';
        if (empty($data['end_date']))   $errors[] = 'Datum konce je povinné.';
        if ($data['start_date'] && $data['end_date'] && $data['start_date'] > $data['end_date']) {
            $errors[] = 'Datum konce musí být po datu začátku.';
        }
        return $errors;
    }
}
