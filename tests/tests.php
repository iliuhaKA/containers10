<?php

require_once __DIR__ . '/testframework.php';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/database.php';
require_once __DIR__ . '/../modules/page.php';

$testFramework = new TestFramework();

// test 1: check database connection
function testDbConnection() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        return assertExpression($db instanceof Database, 'Database connection OK', 'Database connection FAILED');
    } catch (Exception $e) {
        return assertExpression(false, '', 'Database connection FAILED: ' . $e->getMessage());
    }
}

// test 2: test count method
function testDbCount() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $count = $db->Count("page");
        return assertExpression($count >= 3, "Count returned {$count}", "Count failed: got {$count}");
    } catch (Exception $e) {
        return assertExpression(false, '', 'Count FAILED: ' . $e->getMessage());
    }
}

// test 3: test create method
function testDbCreate() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $id = $db->Create("page", ["title" => "Test Page", "content" => "Test Content"]);
        return assertExpression($id > 0, "Create returned id={$id}", "Create failed");
    } catch (Exception $e) {
        return assertExpression(false, '', 'Create FAILED: ' . $e->getMessage());
    }
}

// test 4: test read method
function testDbRead() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $row = $db->Read("page", 1);
        return assertExpression(
            is_array($row) && isset($row['title']) && $row['title'] === 'Page 1',
            'Read OK',
            'Read FAILED'
        );
    } catch (Exception $e) {
        return assertExpression(false, '', 'Read FAILED: ' . $e->getMessage());
    }
}

// test 5: test update method
function testDbUpdate() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $id = $db->Create("page", ["title" => "Before", "content" => "Before content"]);
        $db->Update("page", $id, ["title" => "After", "content" => "After content"]);
        $row = $db->Read("page", $id);
        return assertExpression(
            $row['title'] === 'After' && $row['content'] === 'After content',
            'Update OK',
            'Update FAILED'
        );
    } catch (Exception $e) {
        return assertExpression(false, '', 'Update FAILED: ' . $e->getMessage());
    }
}

// test 6: test delete method
function testDbDelete() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $id = $db->Create("page", ["title" => "To delete", "content" => "Delete me"]);
        $db->Delete("page", $id);
        $row = $db->Read("page", $id);
        return assertExpression(empty($row), 'Delete OK', 'Delete FAILED');
    } catch (Exception $e) {
        return assertExpression(false, '', 'Delete FAILED: ' . $e->getMessage());
    }
}

// test 7: test execute method
function testDbExecute() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $result = $db->Execute("UPDATE page SET title = title WHERE 1=0");
        return assertExpression($result !== false, 'Execute OK', 'Execute FAILED');
    } catch (Exception $e) {
        return assertExpression(false, '', 'Execute FAILED: ' . $e->getMessage());
    }
}

// test 8: test fetch method
function testDbFetch() {
    global $config;
    try {
        $db = new Database($config["db"]["path"]);
        $rows = $db->Fetch("SELECT * FROM page");
        return assertExpression(
            is_array($rows) && count($rows) >= 3,
            'Fetch OK, rows=' . count($rows),
            'Fetch FAILED'
        );
    } catch (Exception $e) {
        return assertExpression(false, '', 'Fetch FAILED: ' . $e->getMessage());
    }
}

// test 9: test page render
function testPageRender() {
    try {
        $tplPath = __DIR__ . '/test_template.tpl';
        file_put_contents($tplPath, '<h1>{{title}}</h1><p>{{content}}</p>');
        $page = new Page($tplPath);
        $html = $page->Render(["title" => "Hello", "content" => "World"]);
        unlink($tplPath);
        return assertExpression(
            strpos($html, '<h1>Hello</h1>') !== false && strpos($html, '<p>World</p>') !== false,
            'Page render OK',
            'Page render FAILED'
        );
    } catch (Exception $e) {
        return assertExpression(false, '', 'Page render FAILED: ' . $e->getMessage());
    }
}

// test 10: test page render with real template
function testPageRenderRealTemplate() {
    try {
        $page = new Page(__DIR__ . '/../templates/index.tpl');
        $html = $page->Render(["title" => "My Title", "content" => "My Content"]);
        return assertExpression(
            strpos($html, 'My Title') !== false && strpos($html, 'My Content') !== false,
            'Page render real template OK',
            'Page render real template FAILED'
        );
    } catch (Exception $e) {
        return assertExpression(false, '', 'Page render real template FAILED: ' . $e->getMessage());
    }
}

$testFramework->add('Database connection', 'testDbConnection');
$testFramework->add('Database count', 'testDbCount');
$testFramework->add('Database create', 'testDbCreate');
$testFramework->add('Database read', 'testDbRead');
$testFramework->add('Database update', 'testDbUpdate');
$testFramework->add('Database delete', 'testDbDelete');
$testFramework->add('Database execute', 'testDbExecute');
$testFramework->add('Database fetch', 'testDbFetch');
$testFramework->add('Page render', 'testPageRender');
$testFramework->add('Page render real template', 'testPageRenderRealTemplate');

$testFramework->run();

echo $testFramework->getResult() . PHP_EOL;
