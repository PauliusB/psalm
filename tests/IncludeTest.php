<?php
namespace Psalm\Tests;

use Psalm\Checker\FileChecker;

class IncludeTest extends TestCase
{
    /**
     * @dataProvider providerTestValidIncludes
     *
     * @param array<int, string> $filesToCheck
     * @param array<string, string> $files
     * @param bool $hoistConstants
     *
     * @return void
     */
    public function testValidInclude(array $files, array $filesToCheck, $hoistConstants = false)
    {
        $codebase = $this->projectChecker->getCodebase();

        foreach ($files as $filePath => $contents) {
            $this->addFile($filePath, $contents);
            $codebase->scanner->addFilesToShallowScan([$filePath => $filePath]);
        }

        foreach ($filesToCheck as $filePath) {
            $codebase->addFilesToAnalyze([$filePath => $filePath]);
        }

        $codebase->scanFiles();

        $config = $codebase->config;
        $config->hoistConstants = $hoistConstants;

        foreach ($filesToCheck as $filePath) {
            $fileChecker = new FileChecker($this->projectChecker, $filePath, $config->shortenFileName($filePath));
            $fileChecker->analyze();
        }
    }

    /**
     * @dataProvider providerTestInvalidIncludes
     *
     * @param array<int, string> $filesToCheck
     * @param array<string, string> $files
     * @param string $errorMessage
     * @param bool $hoistConstants
     *
     * @return void
     */
    public function testInvalidInclude(
        array $files,
        array $filesToCheck,
        $errorMessage,
        $hoistConstants = false
    ) {
        $codebase = $this->projectChecker->getCodebase();

        foreach ($files as $filePath => $contents) {
            $this->addFile($filePath, $contents);
            $codebase->scanner->addFilesToShallowScan([$filePath => $filePath]);
        }

        foreach ($filesToCheck as $filePath) {
            $codebase->addFilesToAnalyze([$filePath => $filePath]);
        }

        $codebase->scanFiles();

        $this->expectException('\Psalm\Exception\CodeException');
        $this->expectExceptionMessageRegexp('/\b' . preg_quote($errorMessage, '/') . '\b/');

        $config = $codebase->config;
        $config->hoistConstants = $hoistConstants;

        foreach ($filesToCheck as $filePath) {
            $fileChecker = new FileChecker($this->projectChecker, $filePath, $config->shortenFileName($filePath));
            $fileChecker->analyze();
        }
    }

    /**
     * @return array
     */
    public function providerTestValidIncludes()
    {
        return [
            'basicRequire' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        class B {
                            public function foo(): void {
                                (new A)->fooFoo();
                            }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        class A{
                            public function fooFoo(): void {

                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'requireSingleStringType' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        $a = "file1.php";
                        require($a);

                        class B {
                            public function foo(): void {
                                (new A)->fooFoo();
                            }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        class A{
                            public function fooFoo(): void {

                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'nestedRequire' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        class A{
                            public function fooFoo(): void {

                            }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        class B extends A{
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        require("file2.php");

                        class C extends B {
                            public function doFoo(): void {
                                $this->fooFoo();
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php',
                ],
            ],
            'requireNamespace' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        namespace Foo;

                        class A{
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        class B {
                            public function foo(): void {
                                (new Foo\A);
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'requireFunction' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        function fooFoo(): void {

                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        fooFoo();',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'namespacedRequireFunction' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        function fooFoo(): void {

                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        namespace Foo;

                        require("file1.php");

                        \fooFoo();',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'requireConstant' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        const FOO = 5;
                        define("BAR", "bat");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        echo FOO;
                        echo BAR;',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'requireNamespacedWithUse' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        namespace Foo;

                        class A{
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        use Foo\A;

                        class B {
                            public function foo(): void {
                                (new A);
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'noInfiniteRequireLoop' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");
                        require_once("file3.php");

                        class B extends A {
                            public function doFoo(): void {
                                $this->fooFoo();
                            }
                        }

                        class C {}

                        new D();',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require_once("file3.php");

                        class A{
                            public function fooFoo(): void { }
                        }

                        new C();',

                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        require_once("file1.php");

                        class D{ }

                        new C();',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php',
                ],
            ],
            'analyzeAllClasses' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");
                        class B extends A {
                            public function doFoo(): void {
                                $this->fooFoo();
                            }
                        }
                        class C {
                            public function barBar(): void { }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require_once("file1.php");
                        class A{
                            public function fooFoo(): void { }
                        }
                        class D extends C {
                            public function doBar(): void {
                                $this->barBar();
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'loopWithInterdependencies' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");
                        class A {}
                        class D extends C {}
                        new B();',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require_once("file1.php");
                        class C {}
                        class B extends A {}
                        new D();',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
            ],
            'variadicArgs' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");
                        variadicArgs(5, 2, "hello");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        function variadicArgs() : void {
                            $args = func_get_args();
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ],
            ],
            'globalIncludedVar' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        $a = 5;
                        require_once("file2.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require_once("file3.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        function getGlobal() : void {
                            global $a;

                            echo $a;
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ]
            ],
            'returnNamespacedFunctionCallType' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        namespace Foo;

                        class A{
                            function doThing() : void {}
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        namespace Bar;

                        use Foo\A;

                        /** @return A */
                        function getThing() {
                            return new A;
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        require("file2.php");

                        namespace Bat;

                        class C {
                            function boop() : void {
                                \Bar\getThing()->doThing();
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php',
                ],
            ],
            'functionUsedElsewhere' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");
                        require_once("file3.php");
                        function foo() : void {}',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        foo();
                        array_filter([1, 2, 3, 4], "bar");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        function bar(int $i) : bool { return (bool) rand(0, 1); }'
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ],
            ],
            'closureInIncludedFile' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        return function(): string { return "asd"; };',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ],
            ],
            'hoistConstants' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        function bat() : void {
                            echo FOO . BAR;
                        }

                        define("FOO", 5);
                        const BAR = "BAR";',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ],
                'hoist_constants' => true,
            ],
        ];
    }

    /**
     * @return array
     */
    public function providerTestInvalidIncludes()
    {
        return [
            'undefinedMethodInRequire' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        class B {
                            public function foo(): void {
                                (new A)->fooFo();
                            }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        class A{
                            public function fooFoo(): void {

                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
                'error_message' => 'UndefinedMethod',
            ],
            'namespacedRequireFunction' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        function fooFoo(): void {

                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        namespace Foo;

                        require("file1.php");

                        \Foo\fooFoo();',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
                'error_message' => 'UndefinedFunction',
            ],
            'globalIncludedIncorrectVar' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        $a = 5;
                        require_once("file2.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require_once("file3.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        function getGlobal() : void {
                            global $b;

                            echo $a;
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ],
                'error_message' => 'UndefinedVariable'
            ],
            'invalidTraitFunctionReturnInUncheckedFile' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        class B {
                            use A;
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        trait A{
                            public function fooFoo(): string {
                                return 5;
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php',
                ],
                'error_message' => 'InvalidReturnType',
            ],
            'invalidDoubleNestedTraitFunctionReturnInUncheckedFile' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php' => '<?php
                        require("file2.php");

                        namespace Foo;

                        use Bar\B;

                        class C {
                            use B;
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        require("file1.php");

                        namespace Bar;

                        use Bat\A;

                        trait B {
                            use A;
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        namespace Bat;

                        trait A{
                            public function fooFoo(): string {
                                return 5;
                            }
                        }',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file3.php',
                ],
                'error_message' => 'InvalidReturnType',
            ],
            'noHoistConstants' => [
                'files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php' => '<?php
                        require_once("file2.php");',
                    getcwd() . DIRECTORY_SEPARATOR . 'file2.php' => '<?php
                        function bat() : void {
                            echo FOO . BAR;
                        }

                        define("FOO", 5);
                        const BAR = "BAR";',
                ],
                'files_to_check' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'file1.php',
                ],
                'error_message' => 'UndefinedConstant',
            ],
        ];
    }
}
