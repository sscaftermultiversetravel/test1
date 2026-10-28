USE shop_db;
-- 管理員帳號請用 php api/seed_admin.php 建立（需 PHP password_hash）
INSERT INTO Categories (category_name, description) VALUES
('3C 電子', '手機、耳機與周邊'), ('居家生活', '日常用品'), ('書籍文具', '學習與辦公');

INSERT INTO Products (category_id, name, description, price, stock_quantity) VALUES
(1, '藍牙耳機', '主動降噪，續航 30 小時。', 1990.00, 25),
(1, '快充行動電源', '20000mAh，支援 PD 65W。', 1290.00, 40),
(2, '不鏽鋼保溫瓶', '500ml，保冷保溫 12 小時。', 590.00, 60),
(2, '香氛蠟燭', '天然大豆蠟，燃燒 40 小時。', 350.00, 0),
(3, 'React Native 實戰', '行動開發入門到上線。', 680.00, 18),
(3, '方格筆記本三入', 'A5，100 磅道林紙。', 220.00, 120);
