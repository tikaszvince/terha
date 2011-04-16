<?php
class ClassA {
    public function method_a (ClassB $b) {
	}
}

class ClassB {}

$a = new ClassA();
$a->method_a(new ClassA);
