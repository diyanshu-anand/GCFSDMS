# Create Tables

# 19/07/2026 
# """"" 1. Added REFERENCE and corrected REFERENCE ISSUE with 
# programming logics being resolved. 
# 2.Enhanced the architectural bugs 
# related to mapping of delivery boy with newly formed datasets.
# 3. Replaced the entire command if they were buggy has listed the comment wherever they were.
# 4. Added Datatype to the status coloumn as it was missing and was a buggy command to be followed.
# 5. Many tables didn't followed the architectural system thus resulting into fall of the entire state and the table.
# """" ~ " Divyanshu Anand (author)"

if __name__ == "__main__":

    #  company
    run("""
    CREATE TABLE IF NOT EXISTS company (
        company_id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(150) NOT NULL,
        ownername VARCHAR(150),
        email VARCHAR(150),
        phone VARCHAR(20),
        address TEXT,
        logo VARCHAR(255),
        status TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    """)

    #  users
    run("""
    CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('Admin','Manager','Delivery_agent'),
        device_id VARCHAR(255),
        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status TEXT,
        FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE
        )
    """)

    #  customers
    run("""
    CREATE TABLE IF NOT EXISTS customers (
        customer_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        lattitude VARCHAR(50),
        longitude VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE
    )
    """)

    #  products
    run("""
    CREATE TABLE IF NOT EXISTS products (
        product_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        sku VARCHAR(100) UNIQUE,
        category TEXT,
        price DECIMAL(10,2) NOT NULL,
        unit INT DEFAULT 0,
        current_stock INT DEFAULT 0,
        minimum_stock INT DEFAULT 0,
        status TEXT,
        FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE
    )
    """)
    # inventory
    # run("""
    # CREATE TABLE IF NOT EXISTS inventory (
    #     log_id INT AUTO_INCREMENT PRIMARY KEY,
    #     company_id INT NOT NULL,
    #     product_id INT NOT NULL,
    #     delivery_boy INT NOT NULL,
    #     type ENUM('REMOVE','RETURN') NOT NULL,
    #     quantity INT NOT NULL,
    #     remaining_quantity INT NOT NULL,
    #     remarks TEXT,
    #     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    #     FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    #     FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    #     FOREIGN KEY (delivery_boy) REFERENCES delivery(id) ON DELETE SET NULL
    # )
    # """)

    run("""
        CREATE TABLE IF NOT EXISTS inventory (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        product_id INT NOT NULL,
        delivery_boy INT,
        type ENUM('REMOVE','RETURN') NOT NULL,
        quantity INT NOT NULL,
        remaining_quantity INT NOT NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (company_id)
            REFERENCES company(company_id)
            ON DELETE CASCADE,

        FOREIGN KEY (product_id)
            REFERENCES products(product_id)
            ON DELETE CASCADE,

        FOREIGN KEY (delivery_boy)
            REFERENCES users(user_id)
            ON DELETE SET NULL
        )
    """)

    #  orders
    run("""
        CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        customer_id INT NOT NULL,
        delivery_boy INT NULL,

        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        status ENUM(
            'Pending',
            'Accepted',
            'Picked',
            'Out for Delivery',
            'Delivered',
            'Cancelled'
        ) DEFAULT 'Pending',

        payment_status ENUM(
            'Pending',
            'Paid',
            'Failed',
            'Refunded'
        ) DEFAULT 'Pending',

        payment_mode ENUM(
            'Cash',
            'UPI',
            'Card',
            'Net Banking',
            'Cheque'
        ),

        total_amount DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        delivered_at DATETIME DEFAULT NULL,

        FOREIGN KEY (company_id)
            REFERENCES company(company_id)
            ON DELETE CASCADE,

        FOREIGN KEY (customer_id)
            REFERENCES customers(customer_id)
            ON DELETE CASCADE,

        FOREIGN KEY (delivery_boy)
            REFERENCES users(user_id)
            ON DELETE SET NULL
        );
    """)

    #  order_items
    run("""
    CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    )
    """)
    # live location 
    run("""
    CREATE TABLE IF NOT EXISTS live_location (
        delivery_boy INT NOT NULL,
        latitude DECIMAL(10,8) NOT NULL,
        longitude DECIMAL(11,8) NOT NULL,
        accuracy DECIMAL(8,2),          -- GPS accuracy in meters
        speed DECIMAL(8,2),             -- Speed (km/h or m/s)
        battery_percentage TINYINT,     -- 0-100%
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
        FOREIGN KEY (delivery_boy) REFERENCES users(user_id) ON DELETE CASCADE
    )
    """)

    

    #location history
    # """ Wrong one because there should be a relation
    # among the delivery boy column else a new spanning will happen of false 
    # data. DELETE CASCADE needs to be implemented as that would 
    # ensure no orphan data existence.""" 
    # run("""
    # CREATE TABLE IF NOT EXISTS location_history (
    #     delivery_boy INT NOT NULL,
    #     latitude DECIMAL(10,8) NOT NULL,
    #     longitude DECIMAL(11,8) NOT NULL,
    #     time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    # )
    # """)
    run("""
        CREATE TABLE IF NOT EXISTS location_history (
            location_id INT AUTO_INCREMENT PRIMARY KEY,
            delivery_boy INT NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (delivery_boy)
                REFERENCES users(user_id)
                ON DELETE CASCADE
        );
    """)

    # notification 
    # """"" Foreign Keys relations are missing and 
    # time shall be replaced by recorded and 
    # created at with timestamp data type """""
    # run("""
    # CREATE TABLE IF NOT EXISTS notifications (
    #     id INT AUTO_INCREMENT PRIMARY KEY,
    #     company_id INT NOT NULL,
    #     user_id INT NOT NULL,
    #     title VARCHAR(255) NOT NULL,
    #     message TEXT NOT NULL,
    #     is_read BOOLEAN DEFAULT FALSE,
    #     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    # )
    # """)

    run("""
        CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id)
        REFERENCES company(company_id)
        ON DELETE CASCADE,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
)""")

    # attendance
    # """" Foreign relationship of delivery boy with user is 
    # missing......... """"  
    # run("""
    # CREATE TABLE IF NOT EXISTS attendance (
    #     id INT AUTO_INCREMENT PRIMARY KEY,
    #     delivery_boy INT NOT NULL,
    #     login_time DATETIME NOT NULL,
    #     logout_time DATETIME DEFAULT NULL,
    #     working_hours DECIMAL(5,2) DEFAULT 0.00
    # )
    # """)

    run("""
        CREATE TABLE IF NOT EXISTS attendance (
        attendance_id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_boy INT NOT NULL,
        login_time DATETIME NOT NULL,
        logout_time DATETIME DEFAULT NULL,
        working_hours DECIMAL(5,2) DEFAULT 0.00,

        FOREIGN KEY (delivery_boy)
        REFERENCES users(user_id)
        ON DELETE CASCADE
    );""")

    # app_log 
    # """Renamed id → log_id (consistent with your schema)
    #  Removed the stray `````` after JSON
    #  Added created_at to know when the log was generated
    # Added the foreign key:"
    # run("""
    # CREATE TABLE IF NOT EXISTS app_logs (
    #     id INT AUTO_INCREMENT PRIMARY KEY,
    #     user_id INT NOT NULL,
    #     activity VARCHAR(255) NOT NULL,
    #     ip_address VARCHAR(45),
    #     device VARCHAR(255),
    #     data JSON   ``````
    # )
    # """)

    run(""" 
        CREATE TABLE IF NOT EXISTS app_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            device VARCHAR(255),
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id)
                REFERENCES users(user_id)
                ON DELETE CASCADE
        );
    """)

    db.close()
    print("All tables created successfully")