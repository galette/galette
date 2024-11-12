<?php

/**
 * Copyright © 2003-2024 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 *
 * @category Libraries
 * @package  ClassLoader
 * @author Doctrine project <contact@doctrine-project.org>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @license  LGPL https://www.gnu.org/licenses/lgpl-3.0.fr.html
 * @link     http://www.doctrine-project.org - https://galette.eu
 */

declare(strict_types=1);

namespace Galette\Common;

/**
 * A <code>ClassLoader</code> is an autoloader for class files that can be
 * installed on the SPL autoload stack. It is a class loader that either loads only classes
 * of a specific namespace or all namespaces and it is suitable for working together
 * with other autoloaders in the SPL autoload stack.
 *
 * If no include path is configured through the constructor or {@link setIncludePath}, a ClassLoader
 * relies on the PHP <code>include_path</code>.
 *
 * @category Libraries
 * @package  ClassLoader
 * @author Roman Borschel <roman@code-factory.org>
 * @license  LGPL https://www.gnu.org/licenses/lgpl-3.0.fr.html
 * @link     http://www.doctrine-project.org - https://galette.eu
 * @since 2.0
 */
class ClassLoader
{
    /**
     * @var string PHP file extension
     */
    protected string $fileExtension = '.php';

    /**
     * @var ?string Current namespace
     */
    protected ?string $namespace;

    /**
     * @var ?string Current include path
     */
    protected ?string $includePath;

    /**
     * @var string PHP namespace separator
     */
    protected string $namespaceSeparator = '\\';

    /**
     * Creates a new <code>ClassLoader</code> that loads classes of the
     * specified namespace from the specified include path.
     *
     * If no include path is given, the ClassLoader relies on the PHP include_path.
     * If neither a namespace nor an include path is given, the ClassLoader will
     * be responsible for loading all classes, thereby relying on the PHP include_path.
     *
     * @param ?string $ns          The namespace of the classes to load.
     * @param ?string $includePath The base include path to use.
     */
    public function __construct(?string $ns = null, ?string $includePath = null)
    {
        if (!file_exists($includePath)) {
            throw new \RuntimeException('Include path "' . $includePath . '" doesn\'t exists');
        }

        $this->namespace = $ns;
        $this->includePath = $includePath;
    }

    /**
     * Sets the namespace separator used by classes in the namespace of this ClassLoader.
     *
     * @param string $sep The separator to use.
     *
     * @return void
     */
    public function setNamespaceSeparator(string $sep): void
    {
        $this->namespaceSeparator = $sep;
    }

    /**
     * Gets the namespace separator used by classes in the namespace of this ClassLoader.
     *
     * @return string
     */
    public function getNamespaceSeparator(): string
    {
        return $this->namespaceSeparator;
    }

    /**
     * Sets the base include path for all class files in the namespace of this ClassLoader.
     *
     * @param string $includePath Include path
     *
     * @return void
     */
    public function setIncludePath(string $includePath): void
    {
        $this->includePath = $includePath;
    }

    /**
     * Gets the base include path for all class files in the namespace of this ClassLoader.
     *
     * @return string
     */
    public function getIncludePath(): string
    {
        return $this->includePath;
    }

    /**
     * Sets the file extension of class files in the namespace of this ClassLoader.
     *
     * @param string $fileExtension File extension
     *
     * @return void
     */
    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * Gets the file extension of class files in the namespace of this ClassLoader.
     *
     * @return string
     */
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    /**
     * Registers this ClassLoader on the SPL autoload stack.
     *
     * @return void
     */
    public function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $this->loadClass($class);
        });
    }

    /**
     * Removes this ClassLoader from the SPL autoload stack.
     *
     * @return void
     */
    public function unregister(): void
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $className The name of the class to load.
     *
     * @return boolean TRUE if the class has been successfully loaded, FALSE otherwise.
     */
    public function loadClass(string $className): bool
    {
        if ($this->namespace !== null && strpos($className, $this->namespace . $this->namespaceSeparator) !== 0) {
            return false;
        }

        if ($this->namespace !== null) {
            $className = str_replace(
                $this->namespaceSeparator,
                DIRECTORY_SEPARATOR,
                $className
            );
        }

        $path = ($this->includePath !== null ? $this->includePath . DIRECTORY_SEPARATOR : '')
                . $className
                . $this->fileExtension;

        if (file_exists($path)) {
            require $path;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Asks this ClassLoader whether it can potentially load the class (file) with
     * the given name.
     *
     * @param string $className The fully-qualified name of the class.
     * @return boolean TRUE if this ClassLoader can load the class, FALSE otherwise.
     */
    public function canLoadClass(string $className): bool
    {
        if ($this->namespace !== null && strpos($className, $this->namespace . $this->namespaceSeparator) !== 0) {
            return false;
        }

        $file = str_replace($this->namespaceSeparator, DIRECTORY_SEPARATOR, $className) . $this->fileExtension;

        if ($this->includePath !== null) {
            return file_exists($this->includePath . DIRECTORY_SEPARATOR . $file);
        }

        return (false !== stream_resolve_include_path($file));
    }

    /**
     * Checks whether a class with a given name exists. A class "exists" if it is either
     * already defined in the current request or if there is an autoloader on the SPL
     * autoload stack that is a) responsible for the class in question and b) is able to
     * load a class file in which the class definition resides.
     *
     * If the class is not already defined, each autoloader in the SPL autoload stack
     * is asked whether it is able to tell if the class exists. If the autoloader is
     * a <code>ClassLoader</code>, {@link canLoadClass} is used, otherwise the autoload
     * function of the autoloader is invoked and expected to return a value that
     * evaluates to TRUE if the class (file) exists. As soon as one autoloader reports
     * that the class exists, TRUE is returned.
     *
     * Note that, depending on what kinds of autoloaders are installed on the SPL
     * autoload stack, the class (file) might already be loaded as a result of checking
     * for its existence. This is not the case with a <code>ClassLoader</code>, who separates
     * these responsibilities.
     *
     * @param string $className The fully-qualified name of the class.
     * @return boolean TRUE if the class exists as per the definition given above, FALSE otherwise.
     */
    public static function classExists(string $className): bool
    {
        if (class_exists($className, false) || interface_exists($className, false)) {
            return true;
        }

        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader)) { // array(???, ???)
                if (is_object($loader[0])) {
                    if ($loader[0] instanceof ClassLoader) { // array($obj, 'methodName')
                        if ($loader[0]->canLoadClass($className)) {
                            return true;
                        }
                    } elseif ($loader[0]->{$loader[1]}($className)) {
                        return true;
                    }
                } elseif ($loader[0]::$loader[1]($className)) { // array('ClassName', 'methodName')
                    return true;
                }
            } elseif ($loader instanceof \Closure) { // function($className) {..}
                if ($loader($className)) {
                    return true;
                }
            } elseif (is_string($loader) && $loader($className)) { // "MyClass::loadClass"
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the <code>ClassLoader</code> from the SPL autoload stack that is responsible
     * for (and is able to load) the class with the given name.
     *
     * @param string $className The name of the class.
     * @return ClassLoader|null The <code>ClassLoader</code> responsible for the class or NULL if no such
     */
    public static function getClassLoader(string $className): ?ClassLoader
    {
        foreach (spl_autoload_functions() as $loader) {
            if (
                is_array($loader)
                && $loader[0] instanceof ClassLoader
                && $loader[0]->canLoadClass($className)
            ) {
                return $loader[0];
            }
        }

        return null;
    }
}
