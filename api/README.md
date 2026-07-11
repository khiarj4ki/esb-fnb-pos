# API POS
PHP Yii2 API for POS

## Development Setup

### Prerequisites
- Windows User
    - Install [Microsoft Visual C++ 2012 Redistributable](https://www.microsoft.com/en-us/download/details.aspx?id=30679)
    - Install [Microsoft Visual C++ 2013 Redistributable](https://www.microsoft.com/en-us/download/details.aspx?id=40784)
    - Install [Microsoft Visual C++ 2015 Redistributable](https://learn.microsoft.com/en-US/cpp/windows/latest-supported-vc-redist?view=msvc-170)
    - Install web server and mysql server, you can download [Apache24 setup](bit.ly/esb-apache24) and follow the instructions
    - Install [Composer](https://getcomposer.org/download)
    
### Setting Up a Project
<b>Clone the project</b>

```bash
git clone https://gitlab.esb.co.id/esb/esb-pos/fnb-pos-api-v2.git
```

<b>Install composer packages dependencies</b>

```bash
composer install
```

<b>Configuration</b>
Create the following file for local environment configuration:  
- `fnb-pos-api-v2/config/db.php`

```php
# fnb-pos-api-v2/config/db.php
<?php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host={{IP_ADDRESS}};dbname={{DB_NAME}}',
    'username' => '{{MYSQL_USERNAME}}',
    'password' => '{{MYSQL_PASSWORD}}',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    // 'enableSchemaCache' => true,
    // 'schemaCacheDuration' => 3600,
    // 'schemaCache' => 'cache',
];
```

<b>Run DB Migration</b>
```
php yii migrate
```

## System Requirement

**System Operations:** Windows or Unix Base
**PHP:** 7.2 or 7.4
**MYSQL:** 5.6

Here are some related projects
- [FNB POS V2 ANGULAR](https://gitlab.esb.co.id/esb/esb-pos/fnb-pos-v2-angular)
- [FNB Backend](https://gitlab.esb.co.id/esb/esb-core/fnb-backend)
