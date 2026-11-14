USE CINE_DB;

-- Paso 1: la tabla Cliente ya existe y contrasena es VARCHAR(255) (alcanza para BCRYPT, 60 chars).
-- El script principal (CINE_DB_AE4_final.sql) ya carga los usuarios de prueba con hashes BCRYPT,
-- asi que este paso solo hace falta en una base creada con una version anterior (SHA-256).
-- Las contrasenas de prueba NO se documentan aca: se comparten por otro medio.
UPDATE Cliente SET contrasena = '$2y$10$7/zLpbvuEguVhAtZL3Mfp.SoVI8Wb2xb7mfFondAKUohRcuXOu1bi' WHERE email = 'admin@cine.com';
UPDATE Cliente SET contrasena = '$2y$10$zc5QiXJTQhlud8y9NV8KTetpV1ebdLwQDCGeDjSn6SrvP.NZ9fqn2' WHERE email = 'lucia@mail.com';
UPDATE Cliente SET contrasena = '$2y$10$J0Mr6/1i6PuBp6B6fSUMlelyAzfdaHtWjMi/RyosU5.cqV9mKY7n2' WHERE email = 'marcos@mail.com';
UPDATE Cliente SET contrasena = '$2y$10$aMjtDS7BNQBqUnH1FF2M6Od4DQR5NI1op4LfSHKMBvnjI0j.OorV.' WHERE email = 'sofia@mail.com';
UPDATE Cliente SET contrasena = '$2y$10$pGCXQmI.yYtO2Xgcu66FtefoYp4Ht6gn9yXb8XtF6qjK7.8JfJip2' WHERE email = 'tomas@mail.com';
