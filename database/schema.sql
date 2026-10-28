-- ============================================================================
-- 行動商城 shop_db —— 對應 Week03（資料表設計）與 Week04（關聯與 SQL）
-- 執行：phpMyAdmin → SQL 頁籤貼上，或 mariadb -u root -p < schema.sql
-- ============================================================================
CREATE DATABASE IF NOT EXISTS shop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; -- W3：必須 utf8mb4
USE shop_db;

-- 1. Users：UNIQUE email、ENUM 角色、DEFAULT CURRENT_TIMESTAMP
CREATE TABLE Users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash CHAR(60)     NOT NULL,               -- bcrypt 固定 60 字元（W3：定長用 CHAR）
    full_name     VARCHAR(50)  NOT NULL,
    role          ENUM('customer','manager','admin') NOT NULL DEFAULT 'customer',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Categories
CREATE TABLE Categories (
    category_id   INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    description   TEXT
) ENGINE=InnoDB;

-- 3. Products：價格用 DECIMAL、描述用 TEXT、圖片只存網址、CHECK 約束
CREATE TABLE Products (
    product_id     INT AUTO_INCREMENT PRIMARY KEY,
    category_id    INT NULL,
    name           VARCHAR(100)  NOT NULL,
    description    TEXT,
    price          DECIMAL(10,2) NOT NULL,
    stock_quantity INT           NOT NULL DEFAULT 0,
    image_url      VARCHAR(255),
    CONSTRAINT chk_price CHECK (price >= 0),
    CONSTRAINT chk_stock CHECK (stock_quantity >= 0),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES Categories(category_id) ON DELETE SET NULL   -- 分類刪除，商品保留
) ENGINE=InnoDB;

-- 4. Cart：雙外部鍵，帳號或商品刪除時一併清除；同人同商品僅一筆
CREATE TABLE Cart (
    cart_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_cart_qty CHECK (quantity > 0),
    UNIQUE KEY uk_cart_user_product (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES Users(user_id)       ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES Products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Orders：status 只記錄履約進度，不記錄付款
CREATE TABLE Orders (
    order_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status       ENUM('awaiting_shipment','shipped','completed','cancelled') NOT NULL DEFAULT 'awaiting_shipment',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Order_Items：橋樑表破解 Orders ↔ Products 多對多；保留下單當下價格
CREATE TABLE Order_Items (
    item_id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id          INT NOT NULL,
    product_id        INT NULL,                             -- 商品下架 → SET NULL，訂單歷史保留
    quantity          INT NOT NULL,
    price_at_purchase DECIMAL(10,2) NOT NULL,
    CONSTRAINT chk_item_qty CHECK (quantity > 0),
    FOREIGN KEY (order_id)   REFERENCES Orders(order_id)     ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES Products(product_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. Payments：一張訂單一筆付款
CREATE TABLE Payments (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL,
    payment_method VARCHAR(50)  NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    status         VARCHAR(20)  NOT NULL DEFAULT 'pending',
    paid_at        TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_payments_order (order_id),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Reviews
CREATE TABLE Reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    rating     TINYINT NOT NULL,
    comment    TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY uk_review (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES Users(user_id)       ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES Products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. Chat_Rooms：客服聊天室（utf8mb4 才能存表情符號）
CREATE TABLE Chat_Rooms (
    room_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_room_user (user_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Messages
CREATE TABLE Messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id    INT NOT NULL,
    sender_id  INT NOT NULL,
    content    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id)   REFERENCES Chat_Rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id)      ON DELETE CASCADE
) ENGINE=InnoDB;

-- 加速 JOIN／篩選的索引（W4：外鍵之外的查詢路徑）
CREATE INDEX idx_products_category ON Products(category_id);
CREATE INDEX idx_orders_user       ON Orders(user_id, created_at);
