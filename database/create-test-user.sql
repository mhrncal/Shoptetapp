-- Vytvoření testovacího uživatele pro photo review API
-- Spusť: mysql -u user -p database < database/create-test-user.sql

-- Kontrola jestli user neexistuje
DELETE FROM users WHERE id = 1;

-- Vytvoř testovacího uživatele
INSERT INTO users (
    id,
    email,
    password_hash,
    first_name,
    last_name,
    shop_name,
    shop_url,
    role,
    status,
    created_at
) VALUES (
    1,
    'test@shopcode.cz',
    '$2y$10$abcdefghijklmnopqrstuv.ABCDEFGHIJKLMNOPQRSTUV',  -- dummy hash (nelze se přihlásit)
    'Test',
    'User',
    'Test Obchod',
    'https://test-obchod.cz',
    'user',
    'approved',
    NOW()
);

-- Informace
SELECT 
    id, 
    email, 
    CONCAT(first_name, ' ', last_name) AS name,
    shop_name,
    role,
    status 
FROM users 
WHERE id = 1;
