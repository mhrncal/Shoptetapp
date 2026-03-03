-- Změna role uživatele pro testování API
-- Vytvoříme user s ID=3 nebo změníme existujícího

-- VARIANTA A: Změň superadmina na usera (dočasně)
-- Odkomentuj pokud chceš změnit existujícího:
-- UPDATE users SET role = 'user' WHERE id = 2;

-- VARIANTA B: Vytvoř nového test usera
INSERT INTO users (
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
    'obchod@test.cz',
    '$2y$10$dummy.hash.pro.test.ucet.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Test',
    'Obchod',
    'Test E-shop',
    'https://test-eshop.cz',
    'user',
    'approved',
    NOW()
);

-- Zobraz všechny uživatele
SELECT 
    id,
    email,
    CONCAT(first_name, ' ', last_name) AS jmeno,
    shop_name,
    role,
    status
FROM users
ORDER BY id;
