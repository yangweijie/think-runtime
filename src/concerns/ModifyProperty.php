<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\concerns;

use ReflectionObject;

trait ModifyProperty
{
    /**
     * 修改对象的私有或受保护属性
     *
     * @param object $object 目标对象
     * @param mixed $value 新值
     * @param string $property 属性名称
     * @return void
     */
    protected function modifyProperty(object $object, mixed $value, string $property = 'app'): void
    {
        $reflectObject = new ReflectionObject($object);
        if ($reflectObject->hasProperty($property)) {
            $reflectProperty = $reflectObject->getProperty($property);
            $reflectProperty->setAccessible(true);
            $reflectProperty->setValue($object, $value);
        }
    }
}
