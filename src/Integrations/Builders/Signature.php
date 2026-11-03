<?php
/**
 * Checks a builder base class before CFMB extends it.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

defined( 'ABSPATH' ) || exit;

/**
 * A subclass whose method signatures don't match its parent is a fatal
 * error the moment the file loads. Builders such as Breakdance and Oxygen
 * don't publish the exact signatures of their base classes, so each CFMB
 * subclass lists the methods it declares and their return types, and is
 * only loaded when the parent agrees:
 *
 * - every abstract method of the parent is declared by CFMB
 * - where the parent declares a return type, CFMB declares the same one
 * - where CFMB declares none, the parent declares none
 */
final class Signature {

	/**
	 * Whether a CFMB class with these methods can safely extend a parent.
	 *
	 * @param string                $parent  Parent class name.
	 * @param array<string, string> $methods Method name => return type ('' for none).
	 * @return bool
	 */
	public static function fits( string $parent, array $methods ): bool {
		if ( ! class_exists( $parent ) ) {
			return false;
		}

		try {
			$class = new ReflectionClass( $parent );
		} catch ( ReflectionException $e ) {
			return false;
		}

		foreach ( $class->getMethods() as $method ) {
			if ( $method->isAbstract() && ! isset( $methods[ $method->getName() ] ) ) {
				return false;
			}
		}

		foreach ( $methods as $name => $ours ) {
			if ( ! $class->hasMethod( $name ) ) {
				continue;
			}

			$method = $class->getMethod( $name );

			if ( $method->isFinal() || $method->isStatic() ) {
				return false;
			}

			$theirs = $method->getReturnType();

			if ( null === $theirs ) {
				continue;
			}

			if ( '' === $ours || ! $theirs instanceof ReflectionNamedType || ! self::same( $theirs, $ours ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a declared return type names the same type as ours.
	 *
	 * @param ReflectionNamedType $type Parent's type.
	 * @param string              $ours Our type, possibly with a leading ? or backslash.
	 * @return bool
	 */
	private static function same( ReflectionNamedType $type, string $ours ): bool {
		$nullable = 0 === strpos( $ours, '?' );
		$name     = ltrim( $nullable ? substr( $ours, 1 ) : $ours, '\\' );

		return strtolower( ltrim( $type->getName(), '\\' ) ) === strtolower( $name ) && $type->allowsNull() === ( $nullable || 'mixed' === $name );
	}
}
