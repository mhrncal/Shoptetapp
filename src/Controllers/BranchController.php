<?php
namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\Branch;

class BranchController extends BaseController
{
    public function index(): void
    {
        $userId   = $this->user['id'];
        $branches = Branch::allForUser($userId);
        // Přidáme otevírací doby ke každé pobočce
        foreach ($branches as &$b) {
            $b['hours'] = Branch::getHours($b['id']);
        }
        $this->view('branches/index', [
            'pageTitle' => 'Pobočky',
            'branches'  => $branches,
            'days'      => Branch::DAYS,
        ]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $data   = $this->extractData();

        if (empty($data['name'])) {
            Session::flash('error', 'Název pobočky je povinný.');
            $this->redirect('/branches');
        }

        $id    = Branch::create($userId, $data);
        $hours = $this->extractHours();
        Branch::saveHours($id, $hours);

        Session::flash('success', 'Pobočka byla přidána.');
        $this->redirect('/branches');
    }

    public function edit(): void
    {
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $branch = Branch::findById($id, $userId);
        if (!$branch) Response::notFound();

        $branch['hours'] = Branch::getHours($id);
        $this->view('branches/edit', [
            'pageTitle' => 'Upravit pobočku',
            'branch'    => $branch,
            'days'      => Branch::DAYS,
        ]);
    }

    public function update(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $branch = Branch::findById($id, $userId);
        if (!$branch) Response::notFound();

        $data = $this->extractData();
        if (empty($data['name'])) {
            Session::flash('error', 'Název pobočky je povinný.');
            $this->redirect('/branches/' . $id);
        }

        Branch::update($id, $userId, $data);
        Branch::saveHours($id, $this->extractHours());
        Session::flash('success', 'Pobočka byla uložena.');
        $this->redirect('/branches');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $ok     = Branch::delete($id, $userId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Pobočka smazána.' : 'Pobočka nenalezena.');
        $this->redirect('/branches');
    }

    private function extractData(): array
    {
        return [
            'name'           => trim($this->request->post('name', '')),
            'description'    => trim($this->request->post('description', '')),
            'street_address' => trim($this->request->post('street_address', '')),
            'city'           => trim($this->request->post('city', '')),
            'postal_code'    => trim($this->request->post('postal_code', '')),
            'image_url'      => trim($this->request->post('image_url', '')),
            'branch_url'     => trim($this->request->post('branch_url', '')),
            'google_maps_url'=> trim($this->request->post('google_maps_url', '')),
            'latitude'       => $this->request->post('latitude') ?: null,
            'longitude'      => $this->request->post('longitude') ?: null,
        ];
    }

    private function extractHours(): array
    {
        $raw   = $this->request->post('hours', []);
        $hours = [];
        for ($d = 0; $d <= 6; $d++) {
            $hours[$d] = [
                'is_closed' => !empty($raw[$d]['is_closed']),
                'open_from' => $raw[$d]['open_from'] ?? null,
                'open_to'   => $raw[$d]['open_to']   ?? null,
                'note'      => $raw[$d]['note']       ?? null,
            ];
        }
        return $hours;
    }
}
