<?php

namespace Codebender\CompilerBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerFunctionalTest extends WebTestCase
{
    public function testStatus()
    {
        $client = static::createClient();

        $client->request('GET', '/status');

        $this->assertEquals($client->getResponse()->getContent(), '{"success":true,"status":"OK"}');

    }

    public function testInvalidKey()
    {
        $client = static::createClient();

        $client->request('GET', '/inValidKey/v1');

        $this->assertEquals($client->getResponse()->getContent(), '{"success":false,"step":0,"message":"Invalid authorization key."}');

    }

    public function testInvalidAPI()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('GET', '/' . $authorizationKey . '/v666');

        $this->assertEquals($client->getResponse()->getContent(), '{"success":false,"step":0,"message":"Invalid API version."}');

    }

    public function testInvalidInput()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('GET', '/' . $authorizationKey . '/v1');

        $this->assertEquals($client->getResponse()->getContent(), '{"success":false,"step":0,"message":"Invalid input."}');

    }

    public function testBlinkUnoSyntaxCheck()
    {
        $files = array(array("filename" => "Blink.ino", "content" => "int led = 13;\nvoid setup() {pinMode(led, OUTPUT);}\nvoid loop() {\ndigitalWrite(led, HIGH);\ndelay(1000);\ndigitalWrite(led, LOW);\ndelay(1000);\n}\n"));
        $format = "syntax";
        $version = "105";
        $libraries = array();
        $build = array("mcu" => "atmega328p", "f_cpu" => "16000000", "core" => "arduino", "variant" => "standard");

        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response["success"], true);
        $this->assertTrue(is_numeric($response["time"]));

    }

    public function testBlinkUnoCompile()
    {
        $files = array(array("filename" => "Blink.ino", "content" => "\nint led = 13;\nvoid setup() {\npinMode(led, OUTPUT);\n}\nvoid loop() {\ndigitalWrite(led, HIGH);\ndelay(1000);\ndigitalWrite(led, LOW);\ndelay(1000);\n}\n"));
        $format = "binary";
        $version = "105";
        $libraries = array();
        $build = array("mcu" => "atmega328p", "f_cpu" => "16000000", "core" => "arduino", "variant" => "standard");

        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response["success"], true);
        $this->assertTrue(is_numeric($response["time"]));
        $this->assertTrue(is_numeric($response["size"]));

        $objectFilesPath = $client->getContainer()->getParameter('temp_dir') . '/' . $client->getContainer()->getParameter('objdir');
        $coreObjectLibrary = glob("$objectFilesPath/*__v105__hardware__arduino__cores__arduino________atmega328p_16000000_arduino_standard_null_null_______core.a");
        $this->assertTrue(count($coreObjectLibrary) > 0);
    }

    public function testBlinkUnoSyntaxCheckError()
    {
        $files = array(array("filename" => "Blink.ino", "content" => "\nint led = 13\nvoid setup() {\npinMode(led, OUTPUT);\npinMode(led);\n}\nvoid loop() {\ndigitalWrite(led, HIGH);\ndelay(1000);\ndigitalWrite(led, LOW);\ndelay(1000);\n}\n"));
        $format = "syntax";
        $version = "105";
        $libraries = array();
        $build = array("mcu" => "atmega328p", "f_cpu" => "16000000", "core" => "arduino", "variant" => "standard");

        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response["success"], false);
        $this->assertEquals($response["success"], false);
        $this->assertEquals($response["step"], 4);
        $this->assertContains("Blink.ino:2:13:", $response["message"]);
        $this->assertContains("expected ';' after top level declarator", $response["message"]);
        $this->assertContains("no matching function for call to 'pinMode'", $response["message"]);
        $this->assertContains("candidate function not viable: requires 2 arguments, but 1 was provided", $response["message"]);
        // $this->assertContains("2 errors generated.", $response["message"]); //unfortunately we no longer show how many errors were generated
    }

    public function testBlinkUnoCompileError()
    {
        $files = array(array("filename" => "Blink.ino", "content" => "\nint led = 13\nvoid setup() {\npinMode(led, OUTPUT);\npinMode(led);\n}\nvoid loop() {\ndigitalWrite(led, HIGH);\ndelay(1000);\ndigitalWrite(led, LOW);\n  delay(1000);\n}\n"));
        $format = "binary";
        $version = "105";
        $libraries = array();
        $build = array("mcu" => "atmega328p", "f_cpu" => "16000000", "core" => "arduino", "variant" => "standard");

        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response["success"], false);
        $this->assertEquals($response["step"], 4);
        $this->assertContains("Blink.ino:2:13:", $response["message"]);
        $this->assertContains("expected ';' after top level declarator", $response["message"]);
        $this->assertContains("no matching function for call to 'pinMode'", $response["message"]);
        $this->assertContains("candidate function not viable: requires 2 arguments, but 1 was provided", $response["message"]);
        // $this->assertContains("2 errors generated.", $response["message"]);  //unfortunately we no longer show how many errors were generated
    }

    public function testExternalVariant()
    {
        $files = array(array('filename' => 'Blink.ino', 'content' => "void setup(){}\nvoid loop(){}\n"));
        $format = 'binary';
        $version = '105';
        $libraries = array();
        $build = array('mcu' => 'atmega32u4', 'f_cpu' => '8000000', 'core' => 'arduino', 'variant' => 'flora', 'pid' => '0x8004', 'vid' => '0x239A');
        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response['success'], true);
        $objectFilesPath = $client->getContainer()->getParameter('temp_dir') . '/' . $client->getContainer()->getParameter('objdir');
        $coreObjectLibrary = glob("$objectFilesPath/*v105__hardware__arduino__cores__arduino________atmega32u4_8000000_arduino_flora_0x239A_0x8004_______core.a");

        $this->assertTrue(count($coreObjectLibrary) > 0);
    }

    public function testExternalCore()
    {
        $files = array(array('filename' => 'Blink.ino', 'content' => "void setup(){}\nvoid loop(){}\n"));
        $format = 'binary';
        $version = '105';
        $libraries = array();
        $build = array('mcu' => 'attiny85', 'f_cpu' => '8000000', 'core' => 'tiny');
        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response['success'], true);
        $objectFilesPath = $client->getContainer()->getParameter('temp_dir') . '/' . $client->getContainer()->getParameter('objdir');
        $externalCoresPath = pathinfo($client->getContainer()->getParameter('external_core_files'), PATHINFO_BASENAME);
        $coreObjectLibrary = glob("$objectFilesPath/*__{$externalCoresPath}__tiny__cores__tiny________attiny85_8000000_tiny__null_null_______core.a");

        $this->assertTrue(count($coreObjectLibrary) > 0);
    }

    public function testArchiveIsCreated()
    {
        $files = array(array('filename' => 'Blink.ino', 'content' => "void setup(){}\nvoid loop(){}\n"));
        $format = 'binary';
        $version = '105';
        $libraries = array();
        $build = array('mcu' => 'atmega328p', 'f_cpu' => '16000000', 'core' => 'arduino', 'variant' => 'standard');
        $data = json_encode(array('files' => $files, 'archive' => true, 'format' => $format, 'version' => $version, 'libraries' => $libraries, 'build' => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);


        $this->assertTrue(file_exists($response['archive']));
    }

    public function testCleanedUpLinkerError()
    {
        $files = array(array('filename' => 'Linker.ino', 'content' => 'void loop() { }'));
        $format = 'binary';
        $version = '105';
        $libraries = array();
        $build = array('mcu' => 'atmega328p', 'f_cpu' => '16000000', 'core' => 'arduino', 'variant' => 'standard');
        $data = json_encode(array('files' => $files, 'archive' => true, 'format' => $format, 'version' => $version, 'libraries' => $libraries, 'build' => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter('authorizationKey');

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $expectedLinkerError = "core.a(main.o): In function `main':
main.cpp:(.text.main+0x8): undefined reference to `setup'";

        $this->assertFalse($response['success']);
        $this->assertEquals($expectedLinkerError, $response['message']);
    }

    public function testEthernetCompileErrorRemovedLibraryPaths()
    {
        $files = array(array("filename" => "Blink.ino", "content" => "#include <Ethernet.h>\nvoid setup() {\n}\nvoid loop() {\n}\n"));
        $format = "binary";
        $version = "105";
        $libraries = array('PseudoEthernet' => array('files' => array('filename' => 'Ethernet.h', 'content' => "#include \"SPI.h\"\n")));
        $build = array("mcu" => "atmega328p", "f_cpu" => "16000000", "core" => "arduino", "variant" => "standard");

        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response["success"], false);
        $this->assertEquals($response["step"], 4);
        $this->assertContains('(library file) PseudoEthernet/Ethernet.h:1:10: </b><b><font style="color: red">fatal error: </font></b><b>\'SPI.h\' file not found', $response['message']);
    }

    public function testEthernetCompileErrorRemovedPersonalLibraryPaths()
    {
        $files = array(array("filename" => "Blink.ino", "content" => "#include <Ethernet.h>\nvoid setup() {\n}\nvoid loop() {\n}\n"));
        $format = "binary";
        $version = "105";
        $libraries = array('4096_cb_personal_lib_PseudoEthernet' => array('files' => array('filename' => 'Ethernet.h', 'content' => "#include \"SPI.h\"\n")));
        $build = array("mcu" => "atmega328p", "f_cpu" => "16000000", "core" => "arduino", "variant" => "standard");

        $data = json_encode(array("files" => $files, "format" => $format, "version" => $version, "libraries" => $libraries, "build" => $build));

        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1', array(), array(), array(), $data);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($response["success"], false);
        $this->assertEquals($response["step"], 4);
        $this->assertContains('(personal library file) PseudoEthernet/Ethernet.h:1:10: </b><b><font style="color: red">fatal error: </font></b><b>\'SPI.h\' file not found', $response['message']);
    }

    public function testDeleteTinyCoreFiles()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1/delete/code/tiny');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertContains('tiny__null_null_______core.a' . "\n", $response['deletedFiles']);
        $this->assertContains('tiny__null_null_______core.a.LOCK', $response['deletedFiles']);
        $this->assertEmpty($response['notDeletedFiles']);

        $tempDirectory = $client->getContainer()->getParameter('temp_dir');
        $objectsDirectory = $client->getContainer()->getParameter('objdir');
        $objectsPath = $tempDirectory . '/' . $objectsDirectory;

        $fileSystemIterator = new \FilesystemIterator($objectsPath);
        foreach ($fileSystemIterator as $file) {
            $this->assertNotContains('tiny__null_null_______core.a' . "\n", $file->getFilename());
            $this->assertNotContains('tiny__null_null_______core.a.LOCK', $file->getFilename());
        }
    }

    public function testDeleteAllCachedObjects()
    {
        $client = static::createClient();

        $authorizationKey = $client->getContainer()->getParameter("authorizationKey");

        $client->request('POST', '/' . $authorizationKey . '/v1/delete/all/');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEmpty($response['Files not deleted']);

        $tempDirectory = $client->getContainer()->getParameter('temp_dir');
        $objectsDirectory = $client->getContainer()->getParameter('objdir');
        $objectsPath = $tempDirectory . '/' . $objectsDirectory;

        $fileSystemIterator = new \FilesystemIterator($objectsPath);
        $this->assertEquals(0, iterator_count($fileSystemIterator));
    }

    public function testAutocomplete()
    {
        $this->markTestIncomplete('No tests for the code completion feature yet.');
    }

    public function testIncorrectInputs()
    {
        $this->markTestIncomplete('No tests for invalid inputs yet');
    }
}
