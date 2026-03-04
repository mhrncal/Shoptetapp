# XML Import - Cleanup

## Co bylo SMAZÁNO (admin UI):

1. ✅ Routes `/xml*` (4 routes)
   - GET  /xml
   - POST /xml/start
   - GET  /xml/status  
   - POST /xml/cancel

2. ✅ Menu položka "XML Import"

3. ✅ XmlController (admin interface)

4. ✅ Views složka src/Views/xml/

## Co ZŮSTÁVÁ (funkční):

1. ✅ CRON job: cron/xml_import.php
   - Funguje automaticky každý den
   - Generuje XML feedy pro recenze
   
2. ✅ Model: src/Models/XmlImport.php
   - Používá CRON
   
3. ✅ DB tabulky:
   - xml_imports
   - xml_import_queue
   - xml_import_log
   
4. ✅ Admin monitoring: /admin/xml-queue
   - Pro adminy vidět historii

## Důvod:

Administrátor nepotřebuje manuální XML import v UI.
Vše běží automaticky přes CRON + nové "Importy produktů" (/feeds).

XML import starý systém → nahrazen "Importy produktů"
