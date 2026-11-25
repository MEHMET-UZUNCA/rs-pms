-- Fidelio uyumlu PROISV şeması (yalnız MySQL/MariaDB sunucusunda, bir kez çalıştırılır).
-- Uygulama view'ları ana veritabanında (rs_pms) pro_isv_* adlarıyla oluşturur (migration).
-- Bu betik, Fidelio'daki gibi ayrı bir "proisv" şeması ve yalnız okuma yetkili bir kullanıcı açar;
-- raporlar ve dış yazılımlar Fidelio'daki sorgularını yalnız bağlantı adresini değiştirerek çalıştırır.
--
-- Kullanım:  mysql -u root -p < database/sql/proisv-schema.sql
-- Önce aşağıdaki şifreyi değiştirin; ana veritabanı adı farklıysa "rs_pms" geçen yerleri düzeltin.

CREATE DATABASE IF NOT EXISTS proisv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_POSTINGS             AS SELECT * FROM rs_pms.pro_isv_postings;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_RESERVATIONINFO      AS SELECT * FROM rs_pms.pro_isv_reservationinfo;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_RESERVATIONREVENUE   AS SELECT * FROM rs_pms.pro_isv_reservationrevenue;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_PROFILEINFO          AS SELECT * FROM rs_pms.pro_isv_profileinfo;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_IND_FAM_LINKS        AS SELECT * FROM rs_pms.pro_isv_ind_fam_links;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_MEMBERCARDINFO       AS SELECT * FROM rs_pms.pro_isv_membercardinfo;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_MULTIGUEST_LIST      AS SELECT * FROM rs_pms.pro_isv_multiguest_list;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_PROMOTIONS           AS SELECT * FROM rs_pms.pro_isv_promotions;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_GIYKIMBIL                AS SELECT * FROM rs_pms.pro_giykimbil;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_DAILY_ROOMBED_RMTYP  AS SELECT * FROM rs_pms.pro_isv_daily_roombed_rmtyp;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_OOOROOMBEDS_ROOMTYPE AS SELECT * FROM rs_pms.pro_isv_oooroombeds_roomtype;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_VIRTUALNUMBERS       AS SELECT * FROM rs_pms.pro_isv_virtualnumbers;
CREATE OR REPLACE SQL SECURITY DEFINER VIEW proisv.PRO_ISV_SAMPLE_INHOUSE       AS SELECT * FROM rs_pms.pro_isv_sample_inhouse;

CREATE USER IF NOT EXISTS 'proisv_okuma'@'%' IDENTIFIED BY 'BURAYA-GUCLU-BIR-SIFRE';
GRANT SELECT ON proisv.* TO 'proisv_okuma'@'%';
FLUSH PRIVILEGES;
