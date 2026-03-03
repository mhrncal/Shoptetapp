/**
 * ShopCode Fotorecenze Widget pro Shoptet
 * 
 * Použití:
 * 1. Vlož tento soubor do Shoptet
 * 2. Přidej tlačítko s class="photoRecension" a data-sku="KOD_PRODUKTU"
 * 3. Hotovo!
 */

(function($) {
    'use strict';
    
    // Konfigurace
    const CONFIG = {
        apiUrl: 'https://aplikace.shopcode.cz/public/api/submit-review.php',
        userId: 1, // ID tvého e-shopu v ShopCode
        maxFileSize: 10 * 1024 * 1024 // 10 MB
    };
    
    // Otevření formuláře při kliknutí
    $(document).on('click', '.photoRecension', function(e) {
        e.preventDefault();
        
        const sku = $(this).data('sku') || '';
        
        $.colorbox({
            html: generateFormHTML(sku),
            maxWidth: "860px",
            maxHeight: "95%",
            height: "95%",
            width: "95%",
            className: "photoRecensionColorbox",
            onComplete: function() {
                initializeForm(sku);
            }
        });
    });
    
    // Generování HTML formuláře
    function generateFormHTML(sku) {
        return `
            <div class="photoRecensionModal">
                <h4 style="margin-bottom: 1rem; color: #333;">Podělte se s námi o váš pěstitelský úspěch</h4>
                <p style="color: #666; margin-bottom: 1.5rem;">
                    Pěstujete tuto rostlinu na své zahradě? Nahrajte vlastní fotografii do galerie tohoto produktu a podělte se 
                    s ostatními, jak Vám rostlina kvete nebo plodí.
                </p>

                <form class="photoRecensionForm" id="photoRecensionFormSubmit">
                    <div class="photoRecensionGrid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

                        <div class="photoRecensionLeft">
                            <label class="photoUploadBox" id="uploadBox" style="
                                border: 2px dashed #ccc;
                                border-radius: 8px;
                                padding: 3rem 1.5rem;
                                text-align: center;
                                cursor: pointer;
                                display: flex;
                                flex-direction: column;
                                justify-content: center;
                                align-items: center;
                                background: #f8f9fa;
                                min-height: 320px;
                                transition: all 0.3s;
                            ">
                                <input type="file" name="photos[]" id="photoInput" accept="image/*" style="display: none;" required>
                                <div style="
                                    width: 80px;
                                    height: 80px;
                                    margin-bottom: 1rem;
                                    background: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22%236c757d%22 viewBox=%220 0 24 24%22%3E%3Cpath d=%22M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z%22/%3E%3C/svg%3E') no-repeat center;
                                    background-size: contain;
                                " id="uploadIcon"></div>
                                <span id="uploadText" style="color: #6c757d; font-size: 0.9rem; line-height: 1.6;">
                                    Klikněte sem nebo sem přetáhněte svou fotografii.<br><br>
                                    <small>
                                        Minimální rozměry jsou 1024 x 768 px, s ideálním poměrem stran 4:3.<br>
                                        Maximální velikost souboru je 10 MB.<br>
                                        Fotografie nesmí obsahovat nevhodný, nelegální nebo jinak závadný obsah.
                                    </small>
                                </span>
                            </label>
                        </div>

                        <div class="photoRecensionRight">
                            <div class="formRow" style="margin-bottom: 1rem;">
                                <input type="text" name="name" placeholder="Vaše jméno *" required style="
                                    width: 100%;
                                    padding: 0.75rem;
                                    border: 1px solid #ced4da;
                                    border-radius: 6px;
                                    font-size: 0.95rem;
                                ">
                            </div>

                            <div class="formRow" style="margin-bottom: 1rem;">
                                <input type="email" name="email" placeholder="Váš e-mail *" required style="
                                    width: 100%;
                                    padding: 0.75rem;
                                    border: 1px solid #ced4da;
                                    border-radius: 6px;
                                    font-size: 0.95rem;
                                ">
                            </div>

                            <div class="photoRecensionConsent" style="
                                background: #fff3cd;
                                border: 1px solid #ffc107;
                                border-radius: 6px;
                                padding: 1rem;
                                font-size: 0.85rem;
                                color: #856404;
                                margin-bottom: 1rem;
                            ">
                                <p style="margin-bottom: 0.5rem;">
                                    <strong>Odesláním fotografie potvrzujete</strong>, že jste autorem fotografie, máte práva k jejímu použití 
                                    a zároveň neporušuje práva třetích osob.
                                </p>
                                <p style="margin: 0;">
                                    Současně udělujete provozovateli e-shopu bezplatnou, nevýhradní, časově i územně neomezenou licenci 
                                    k použití fotografie za účelem prezentace produktu a marketingových aktivit. 
                                    Provozovatel e-shopu má právo fotografii kdykoliv odstranit bez udání důvodu.
                                </p>
                            </div>

                            <button type="submit" class="photoRecensionSubmit" id="submitBtn" style="
                                width: 100%;
                                background: #28a745;
                                color: white;
                                border: none;
                                padding: 0.75rem 1.5rem;
                                border-radius: 6px;
                                font-size: 1rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: background 0.3s;
                            ">
                                Odeslat fotografii
                            </button>

                            <div id="responseMessage" style="margin-top: 1rem;"></div>
                        </div>

                    </div>
                    
                    <input type="hidden" name="sku" value="${sku}">
                    <input type="hidden" name="user_id" value="${CONFIG.userId}">
                </form>
            </div>
        `;
    }
    
    // Inicializace funkcionality formuláře
    function initializeForm(sku) {
        const $form = $('#photoRecensionFormSubmit');
        const $photoInput = $('#photoInput');
        const $uploadBox = $('#uploadBox');
        const $uploadText = $('#uploadText');
        const $uploadIcon = $('#uploadIcon');
        const $submitBtn = $('#submitBtn');
        const $responseMessage = $('#responseMessage');
        
        // File upload - změna
        $photoInput.on('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                if (file.size > CONFIG.maxFileSize) {
                    alert('Soubor je příliš velký! Maximum je 10 MB.');
                    this.value = '';
                    return;
                }
                
                $uploadBox.css({
                    'border-color': '#28a745',
                    'background': '#d4edda'
                });
                
                $uploadIcon.css('background-image', 
                    "url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22%2328a745%22 viewBox=%220 0 24 24%22%3E%3Cpath d=%22M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z%22/%3E%3C/svg%3E')");
                
                $uploadText.html(`
                    <strong style="color: #28a745;">✓ Soubor vybrán</strong><br>
                    ${fileName}<br>
                    <small>${fileSize} MB</small>
                `);
            }
        });
        
        // Drag & Drop
        $uploadBox.on('dragover', function(e) {
            e.preventDefault();
            $(this).css({
                'border-color': '#007bff',
                'background': '#e7f3ff'
            });
        });
        
        $uploadBox.on('dragleave', function(e) {
            e.preventDefault();
            if (!$(this).hasClass('has-file')) {
                $(this).css({
                    'border-color': '#ccc',
                    'background': '#f8f9fa'
                });
            }
        });
        
        $uploadBox.on('drop', function(e) {
            e.preventDefault();
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $photoInput[0].files = files;
                $photoInput.trigger('change');
            }
        });
        
        // Hover efekt na tlačítko
        $submitBtn.hover(
            function() { $(this).css('background', '#218838'); },
            function() { $(this).css('background', '#28a745'); }
        );
        
        // Odeslání formuláře
        $form.on('submit', function(e) {
            e.preventDefault();
            
            $submitBtn.prop('disabled', true).text('Odesílám...');
            $responseMessage.html('');
            
            const formData = new FormData(this);
            
            $.ajax({
                url: CONFIG.apiUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $responseMessage.html(`
                            <div style="
                                background: #d4edda;
                                border: 1px solid #28a745;
                                color: #155724;
                                padding: 1rem;
                                border-radius: 6px;
                            ">
                                <strong>✓ Úspěch!</strong><br>
                                ${response.message || 'Fotografie byla odeslána ke schválení. Děkujeme!'}
                            </div>
                        `);
                        
                        // Zavři ColorBox po 2 sekundách
                        setTimeout(function() {
                            $.colorbox.close();
                        }, 2000);
                        
                    } else {
                        $responseMessage.html(`
                            <div style="
                                background: #f8d7da;
                                border: 1px solid #dc3545;
                                color: #721c24;
                                padding: 1rem;
                                border-radius: 6px;
                            ">
                                <strong>× Chyba!</strong><br>
                                ${response.error || 'Nepodařilo se odeslat fotografii.'}
                            </div>
                        `);
                        $submitBtn.prop('disabled', false).text('Odeslat fotografii');
                    }
                },
                error: function() {
                    $responseMessage.html(`
                        <div style="
                            background: #f8d7da;
                            border: 1px solid #dc3545;
                            color: #721c24;
                            padding: 1rem;
                            border-radius: 6px;
                        ">
                            <strong>× Chyba!</strong><br>
                            Nepodařilo se navázat spojení se serverem.
                        </div>
                    `);
                    $submitBtn.prop('disabled', false).text('Odeslat fotografii');
                }
            });
        });
    }
    
})(jQuery);
