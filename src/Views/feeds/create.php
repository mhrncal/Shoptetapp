<?php $e = fn($v) => htmlspecialchars($v, ENT_QUOTES); ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="h3 mb-4">Nový import produktů</h1>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="/feeds/store">
                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Název importu *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="např. Shoptet produkty">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">URL k CSV feedu *</label>
                        <input type="url" name="url" class="form-control" required
                               placeholder="https://...">
                        <small class="text-muted">
                            Např: https://xxx.myshoptet.com/export/products.csv?patternId=204&hash=...
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Typ CSV</label>
                        <select name="type" class="form-select">
                            <option value="csv_simple">Základní (code, pairCode, name)</option>
                            <option value="csv_with_images" selected>S obrázky (+ sloupce image*)</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Oddělovač</label>
                            <select name="delimiter" class="form-select">
                                <option value=";">; (středník)</option>
                                <option value=",">, (čárka)</option>
                                <option value="\t">Tab</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kódování</label>
                            <select name="encoding" class="form-select">
                                <option value="windows-1250" selected>windows-1250</option>
                                <option value="UTF-8">UTF-8</option>
                                <option value="ISO-8859-2">ISO-8859-2</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="enabled" class="form-check-input" id="enabled" checked>
                        <label class="form-check-label" for="enabled">
                            Povolit automatickou synchronizaci (CRON)
                        </label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Vytvořit import
                        </button>
                        <a href="/feeds" class="btn btn-outline-secondary">Zrušit</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="alert alert-info mt-4">
            <strong>Jak to funguje:</strong>
            <ol class="mb-0 mt-2">
                <li>Zadejte URL k Shoptet CSV exportu s produkty</li>
                <li>Každý den ve 3:00 se stáhne aktuální CSV</li>
                <li>Produkty se uloží do databáze</li>
                <li>Automaticky se spárují fotorecenze podle SKU</li>
                <li>Vygeneruje se XML/CSV s recenzemi + produktovými fotkami</li>
            </ol>
        </div>
    </div>
</div>
