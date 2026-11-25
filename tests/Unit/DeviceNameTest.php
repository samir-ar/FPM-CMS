<?php

namespace Tests\Unit;

use App\Support\DeviceName;
use PHPUnit\Framework\TestCase;

class DeviceNameTest extends TestCase
{
    public function test_iphone_codes_become_the_names_on_the_box()
    {
        $this->assertSame('Apple iPhone 17 Pro Max, iOS 27.2.1', DeviceName::pretty('Apple iPhone18,2, iOS 27.2.1'));
        $this->assertSame('Apple iPhone 17, iOS 26.1', DeviceName::pretty('Apple iPhone18,3, iOS 26.1'));
        $this->assertSame('Apple iPhone 14, iOS 17.2', DeviceName::pretty('Apple iPhone14,7, iOS 17.2'));
        $this->assertSame('Apple iPhone 13, iOS 17.2', DeviceName::pretty('Apple iPhone14,5, iOS 17.2'));
    }

    public function test_unknown_codes_android_and_empty_come_back_unchanged()
    {
        $this->assertSame('Apple iPhone99,9, iOS 30', DeviceName::pretty('Apple iPhone99,9, iOS 30'));
        $this->assertSame('Samsung SM-A525F, Android 13', DeviceName::pretty('Samsung SM-A525F, Android 13'));
        $this->assertSame('', DeviceName::pretty(null));
        $this->assertSame('', DeviceName::pretty(''));
    }
}
