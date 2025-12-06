DROP TABLE "subregions";
CREATE TABLE "subregions" ("id" integer primary key autoincrement not null, "abbr" varchar, "isoSubRegionCode" varchar, "nameDe" varchar not null, "nameEn" varchar, "nation_id" integer, "lsvCode" varchar, "bsvCode" varchar, "created_at" datetime, "updated_at" datetime, foreign key("nation_id") references "nations"("id") on delete set null);

INSERT INTO "subregions" ("id", "abbr", "isoSubRegionCode", "nameDe", "nameEn", "nation_id", "lsvCode", "bsvCode", "created_at", "updated_at") VALUES
(1, 'BL', 'AT-1', 'Burgenland', 'Burgenland', 15, 'BLSV', 'BBSV', '2024-08-20 11:09:26', '2024-08-20 11:59:22'),
(2, 'KN', 'AT-2', 'Kärnten', 'Carinthia', 15, 'KLSV', NULL, '2024-08-20 12:05:32', '2024-08-20 12:17:47'),
(4, 'NO', 'AT-3', 'Niederösterreich', 'Lower Austria', 15, 'NOELSV', NULL, '2024-08-20 13:24:29', '2024-08-20 13:24:29'),
(5, 'OO', 'AT-4', 'Oberösterreich', 'Upper Austria', 15, 'OOELSV', 'OOBSV', '2024-08-20 13:25:02', '2024-08-20 13:25:02'),
(6, 'SB', 'AT-5', 'Salzburg', 'Salzburg', 15, 'SLSV', NULL, '2024-08-20 13:25:36', '2024-08-20 13:25:36'),
(7, 'SM', 'AT-6', 'Steiermark', 'Styria', 15, 'STLSV', NULL, '2024-08-20 13:26:16', '2024-08-20 13:26:16'),
(8, 'TI', 'AT-7', 'Tirol', 'Tyrol', 15, 'TLSV', NULL, '2024-08-20 13:26:48', '2024-08-20 13:26:48'),
(9, 'VB', 'AT-8', 'Vorarlberg', 'Vorarlberg', 15, 'VLSV', NULL, '2024-08-20 13:27:16', '2024-08-20 13:27:16'),
(10, 'WN', 'AT-9', 'Wien', 'Vienna', 15, 'WLSV', NULL, '2024-08-20 13:27:37', '2024-08-20 13:27:37');
