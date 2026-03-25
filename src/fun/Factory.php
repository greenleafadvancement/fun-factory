<?php

namespace dirtsimple\fun;

class Factory implements \ArrayAccess {


	// A chainable invokable

	protected $ops;
	const
		OP_METHOD     = 0,
		OP_PROPERTY   = 1,
		OP_KEY        = 2,
		OP_COND       = 3,
		OP_TAP        = 4,
		OP_VAL        = 5;

	protected function __construct($ops=[]) {
		$this->ops = $ops;
	}

	function __call($name, $args) {
		return $this->andThen(self::OP_METHOD, $name, $args ?: false);
	}

	function __get($name) {
		return $this->andThen(self::OP_PROPERTY, $name);
	}

	protected function andThen($op, $arg=null, $extra=null) {
		$ops = $this->ops;
		$ops[] = [$op, $arg, $extra];
		return new static($ops);
	}

	// --- Interpreter --- //

	function __invoke($_=null) {
		foreach ($this->ops as $opinfo) {
			if ( $op = $opinfo[0] ) {
				if (is_int($op)) {
					if ($op === self::OP_PROPERTY) $_ = $_->{$opinfo[1]};
					elseif ($op === self::OP_KEY)  $_ = is_array($_) ? $_[$opinfo[1]] : $_->offsetGet($opinfo[1]);
					elseif ($op === self::OP_COND) $_ = $opinfo[1]($_) ? $opinfo[2][0]($_) : $opinfo[2][1]($_);
					elseif ($op === self::OP_TAP) $opinfo[1]($_);
					elseif ($op === self::OP_VAL) $_ = $opinfo[1];
					else throw new \DomainException("$op is not a valid fun\\Factory opcode");
				} else {
					$args = $opinfo[1];
					if ($args) { $args[] = $_; $_ = $op(...$args); }
					else $_ = $op($_);
				}
			} else $_ = ($args = $opinfo[2]) ? $_->{$opinfo[1]}(...$args) : $_->{$opinfo[1]}();
		}
		return $_;
	}

	// --- ArrayAccess Implementation --- //

	#[\ReturnTypeWillChange]
	function offsetGet($offset) {
		return $this->andThen(self::OP_KEY, $offset, null);
	}

	#[\ReturnTypeWillChange]
	function offsetExists($offset) {
		return $this->andThen(self::OP_KEY_EXISTS, [$offset]);
	}

	const OP_KEY_EXISTS = [self::class, 'OP_KEY_EXISTS'];

	protected static function OP_KEY_EXISTS($key, $_) {
		return is_array($_) ? array_key_exists($key, $_) : $_->offsetExists($key);
	}

	#[\ReturnTypeWillChange]
	function offsetSet($offset, $value) {
		return $this->andThen(self::OP_SET_KEY, [$offset, $value]);
	}

	const OP_SET_KEY    = [self::class, 'OP_SET_KEY'];

	protected static function OP_SET_KEY($key, $val, $_) {
		$_[$key] = $val; return $_;
	}

	#[\ReturnTypeWillChange]
	function offsetUnset($offset) {
		return $this->andThen(self::OP_UNSET_KEY, [$offset]);
	}

	const OP_UNSET_KEY    = [self::class, 'OP_UNSET_KEY'];

	protected static function OP_UNSET_KEY($key, $_) {
		unset($_[$key]); return $_;
	}

}
