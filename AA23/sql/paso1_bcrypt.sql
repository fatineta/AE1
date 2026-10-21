USE CINE_DB;

-- Paso 1: la tabla Cliente ya existe y contrasena es VARCHAR(255) (alcanza para BCRYPT, 60 chars).
-- Los usuarios de prueba del script original estan en SHA-256: se reemplazan por hashes BCRYPT
-- para que password_verify() funcione. Contrasenas: admin123, lucia123, marcos123, sofia123, tomas123.
UPDATE Cliente SET contrasena = '$2y$10$7/zLpbvuEguVhAtZL3Mfp.SoVI8Wb2xb7mfFondAKUohRcuXOu1bi' WHERE email = 'admin@cine.com';
UPDATE Cliente SET contrasena = '$2y$10$zc5QiXJTQhlud8y9NV8KTetpV1ebdLwQDCGeDjSn6SrvP.NZ9fqn2' WHERE email = 'lucia@mail.com';
UPDATE Cliente SET contrasena = '$2y$10$J0Mr6/1i6PuBp6B6fSUMlelyAzfdaHtWjMi/RyosU5.cqV9mKY7n2' WHERE email = 'marcos@mail.com';
UPDATE Cliente SET contrasena = '$2y$10$aMjtDS7BNQBqUnH1FF2M6Od4DQR5NI1op4LfSHKMBvnjI0j.OorV.' WHERE email = 'sofia@mail.com';
UPDATE Cliente SET contrasena = '$2y$10$pGCXQmI.yYtO2Xgcu66FtefoYp4Ht6gn9yXb8XtF6qjK7.8JfJip2' WHERE email = 'tomas@mail.com';
