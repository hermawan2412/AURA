-- Data SINTETIS untuk uji modul izin di DB lokal (aura_local). Bukan data pegawai asli.
-- Semua akun: kata sandi uji12345.
DELETE FROM user_login; DELETE FROM pegawai;
INSERT INTO pegawai (id, nip, nama_lengkap, jabatan, unit_kerja, status_aktif) VALUES
(1,'190000000000000001','Uji Ketua','Ketua','Pengadilan Agama Rantau',1),
(2,'190000000000000002','Uji Wakil','Wakil Ketua','Pengadilan Agama Rantau',1),
(3,'190000000000000003','Uji Hakim Satu','Hakim','Pengadilan Agama Rantau',1),
(4,'190000000000000004','Uji Hakim Dua','Hakim','Pengadilan Agama Rantau',1),
(5,'190000000000000005','Uji Panitera','Panitera','Pengadilan Agama Rantau',1),
(6,'190000000000000006','Uji Sekretaris','Sekretaris','Pengadilan Agama Rantau',1),
(7,'190000000000000007','Uji Staf Kepaniteraan Satu','Panitera Pengganti','Pengadilan Agama Rantau',1),
(8,'190000000000000008','Uji Staf Kepaniteraan Dua','Juru Sita','Pengadilan Agama Rantau',1),
(9,'190000000000000009','Uji Staf Sekretariat Satu','Pranata Komputer','Pengadilan Agama Rantau',1),
(10,'190000000000000010','Uji Staf Sekretariat Dua','Analis Kepegawaian','Pengadilan Agama Rantau',1);
INSERT INTO user_login (id, username, nip, peran, password_hash, nama_tampilan) VALUES
(1,'ketua','190000000000000001','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Ketua'),
(2,'wakil','190000000000000002','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Wakil'),
(3,'hakim1','190000000000000003','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Hakim Satu'),
(4,'hakim2','190000000000000004','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Hakim Dua'),
(5,'panitera','190000000000000005','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Panitera'),
(6,'sekretaris','190000000000000006','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Sekretaris'),
(7,'kpn1','190000000000000007','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Staf Kepaniteraan Satu'),
(8,'kpn2','190000000000000008','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Staf Kepaniteraan Dua'),
(9,'skr1','190000000000000009','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Staf Sekretariat Satu'),
(10,'skr2','190000000000000010','pengguna','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Staf Sekretariat Dua'),
(11,'admin','','admin','$2y$12$Eqhx6waBt0Z/Y.qWKZjyfurMp7KDvR4DYEH98wi2CObTGmH6V/V9S','Uji Admin');
UPDATE user_login SET nip = NULL WHERE username = 'admin';
