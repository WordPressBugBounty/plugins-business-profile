<?php
/** Interpret public hours as local calendar dates and wall-clock minutes. */
defined( 'ABSPATH' ) || exit;

class bpfwpBusinessHours {
	public static function weekdays() {
		return array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
	}

	/** Null means invalid; zero is a valid midnight. No server timezone is involved. */
	public static function time( $value, $allow_end = false ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( preg_match( '/^(0?[1-9]|1[0-2]):([0-5][0-9])\s*(AM|PM)$/iD', $value, $match ) ) {
			return ( ( (int) $match[1] % 12 ) + ( 'PM' === strtoupper( $match[3] ) ? 12 : 0 ) ) * 60 + (int) $match[2];
		}
		if ( preg_match( '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/D', $value, $match ) ) {
			return (int) $match[1] * 60 + (int) $match[2];
		}
		return $allow_end && '24:00' === $value ? 1440 : null;
	}

	public static function clock( $minutes ) {
		return sprintf( '%02d:%02d', (int) floor( $minutes / 60 ), $minutes % 60 );
	}

	/** Scheduler storage uses ISO dates; slash-separated legacy dates remain accepted. */
	public static function date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '#^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$#D', trim( $value ), $parts ) ) {
			return null;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? sprintf( '%04d-%02d-%02d', $parts[1], $parts[2], $parts[3] ) : null;
	}

	public static function shift_date( $date, $days ) {
		$calendar = new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) );
		return $calendar->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}

	/** A weekly empty time is all-day; an exceptional empty time is a closure. */
	private static function interval( $rule, $exception ) {
		if ( ! isset( $rule['time'] ) || array() === $rule['time'] ) {
			return $exception ? array() : array( 0, 1440 );
		}
		if ( ! is_array( $rule['time'] ) ) {
			return null;
		}
		$from = isset( $rule['time']['start'] ) ? $rule['time']['start'] : '';
		$to   = isset( $rule['time']['end'] ) ? $rule['time']['end'] : '';
		if ( '' === $from && '' === $to ) {
			return $exception ? array() : array( 0, 1440 );
		}
		$start = '' === $from ? 0 : self::time( $from );
		$end   = '' === $to ? 1440 : self::time( $to, true );
		if ( null === $start || null === $end || $start === $end ) {
			return null;
		}
		return array( $start, $end < $start ? $end + 1440 : $end );
	}

	/** Invalid records are diagnosed, not repaired by PHP's permissive date parser. */
	public static function normalize( $weekly, $exceptions ) {
		$result = array(
			'weekly'     => array(),
			'exceptions' => array(),
			'errors'     => array(),
		);
		foreach ( array(
			'weekly'     => $weekly,
			'exceptions' => $exceptions,
		) as $kind => $rules ) {
			if ( null === $rules || '' === $rules || false === $rules ) {
				$rules = array();
			}
			if ( ! is_array( $rules ) || count( $rules ) > 1000 ) {
				$result['errors'][] = array(
					'code'  => 'invalid_schedule',
					'group' => $kind,
					'row'   => null,
				);
				continue;
			}
			foreach ( $rules as $index => $rule ) {
				$interval = is_array( $rule ) ? self::interval( $rule, 'exceptions' === $kind ) : null;
				if ( null === $interval ) {
					$result['errors'][] = array(
						'code'  => 'invalid_time',
						'group' => $kind,
						'row'   => $index,
					);
					continue;
				}
				if ( 'weekly' === $kind ) {
					$days = array();
					foreach ( self::weekdays() as $day ) {
						if ( ! empty( $rule['weekdays'][ $day ] ) ) {
							$days[] = $day;
						}
					}
					if ( ! $days || ! empty( $rule['weeks'] ) ) {
						$result['errors'][] = array(
							'code'  => 'unsupported_weekdays',
							'group' => $kind,
							'row'   => $index,
						);
						continue;
					}
					$result['weekly'][] = array(
						'days'          => $days,
						'interval'      => $interval,
						'missing_start' => empty( $rule['time']['start'] ),
						'missing_end'   => empty( $rule['time']['end'] ),
					);
					continue;
				}
				$single    = ! empty( $rule['date'] );
				$raw_start = $single ? $rule['date'] : ( isset( $rule['date_range']['start'] ) ? $rule['date_range']['start'] : '' );
				$raw_end   = $single ? $rule['date'] : ( isset( $rule['date_range']['end'] ) ? $rule['date_range']['end'] : '' );
				$start     = '' === $raw_start ? null : self::date( $raw_start );
				$end       = '' === $raw_end ? null : self::date( $raw_end );
				if ( ( '' !== $raw_start && null === $start ) || ( '' !== $raw_end && null === $end ) || ( null === $start && null === $end ) || ( $start && $end && $end < $start ) ) {
					$result['errors'][] = array(
						'code'  => 'invalid_date',
						'group' => $kind,
						'row'   => $index,
					);
					continue;
				}
				$result['exceptions'][] = array(
					'start'    => $start,
					'end'      => $end,
					'priority' => $single ? 2 : 1,
					'interval' => $interval,
				);
			}
		}
		return $result;
	}

	public static function merge( $intervals ) {
		usort(
			$intervals,
			function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$result = array();
		foreach ( $intervals as $interval ) {
			$last = count( $result ) - 1;
			if ( $last >= 0 && $interval[0] <= $result[ $last ][1] ) {
				$result[ $last ][1] = max( $result[ $last ][1], $interval[1] );
			} else {
				$result[] = $interval;
			}
		}
		return $result;
	}

	private static function starts_on( $schedule, $date ) {
		$priority  = 0;
		$intervals = array();
		$closed    = false;
		foreach ( $schedule['exceptions'] as $rule ) {
			if ( ( $rule['start'] && $date < $rule['start'] ) || ( $rule['end'] && $date > $rule['end'] ) || $rule['priority'] < $priority ) {
				continue;
			}
			if ( $rule['priority'] > $priority ) {
				$intervals = array();
				$closed    = false;
				$priority  = $rule['priority'];
			}
			if ( ! $rule['interval'] ) {
				$closed = true;
			} else {
				$intervals[] = $rule['interval'];
			}
		}
		if ( ! $priority ) {
			$weekday = strtolower( ( new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) ) )->format( 'l' ) );
			foreach ( $schedule['weekly'] as $rule ) {
				if ( in_array( $weekday, $rule['days'], true ) ) {
					$intervals[] = $rule['interval'];
				}
			}
		}
		return array(
			'exception' => $priority > 0,
			'intervals' => $closed ? array() : self::merge( $intervals ),
		);
	}

	/** Effective intervals within one local date, including previous-day carryover. */
	public static function for_date( $schedule, $date ) {
		$date = self::date( $date );
		if ( null === $date ) {
			return null;
		}
		$today     = self::starts_on( $schedule, $date );
		$intervals = array();
		foreach ( $today['intervals'] as $interval ) {
			$intervals[] = array( $interval[0], min( 1440, $interval[1] ) );
		}
		// An explicit exception replaces all weekly/carryover hours for its date.
		if ( ! $today['exception'] ) {
			$previous = self::starts_on( $schedule, self::shift_date( $date, -1 ) );
			foreach ( $previous['intervals'] as $interval ) {
				if ( $interval[1] > 1440 ) {
					$intervals[] = array( 0, $interval[1] - 1440 );
				}
			}
		}
		return self::merge( $intervals );
	}

	/** Retain one-sided legacy labels while using the same merged effective hours. */
	public static function display_time( $schedule, $day, $interval ) {
		if ( array( 0, 1440 ) === $interval ) {
			return array();
		}
		$time = array(
			'start' => self::clock( $interval[0] ),
			'end'   => self::clock( $interval[1] ),
		);
		foreach ( $schedule['weekly'] as $rule ) {
			if ( ! in_array( $day, $rule['days'], true ) ) {
				continue;
			}
			if ( 0 === $interval[0] && ! empty( $rule['missing_start'] ) && 0 === $rule['interval'][0] ) {
				unset( $time['start'] );
			}
			if ( 1440 === $interval[1] && ! empty( $rule['missing_end'] ) && 1440 === $rule['interval'][1] ) {
				unset( $time['end'] );
			}
		}
		return $time;
	}

	public static function weekly_schema( $schedule ) {
		$result = array();
		foreach ( self::weekdays() as $day ) {
			$intervals = array();
			foreach ( $schedule['weekly'] as $rule ) {
				if ( in_array( $day, $rule['days'], true ) ) {
					$intervals[] = $rule['interval'];
				}
			}
			foreach ( self::merge( $intervals ) as $interval ) {
				$result[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . ucfirst( $day ),
					'opens'     => self::clock( $interval[0] ),
					'closes'    => self::schema_close( $interval ),
				);
			}
		}
		return $result;
	}

	private static function schema_close( $interval ) {
		return 1440 === $interval[1] ? '23:59' : self::clock( $interval[1] % 1440 );
	}

	/** Partition overlapping ranges at calendar boundaries, preserving unbounded sides. */
	public static function exception_schema( $schedule ) {
		$boundaries = array();
		foreach ( $schedule['exceptions'] as $rule ) {
			if ( $rule['start'] ) {
				$boundaries[] = $rule['start'];
			}
			if ( $rule['end'] ) {
				$boundaries[] = self::shift_date( $rule['end'], 1 );
			}
		}
		$boundaries = array_values( array_unique( $boundaries ) );
		// Isolate boundary-adjacent nights so an overnight opening cannot cross a closure.
		foreach ( $boundaries as $boundary ) {
			$boundaries[] = self::shift_date( $boundary, -1 );
			$boundaries[] = self::shift_date( $boundary, 1 );
		}
		$boundaries = array_values( array_unique( $boundaries ) );
		sort( $boundaries );
		if ( ! $boundaries ) {
			return array();
		}
		$result = array();
		for ( $i = 0; $i <= count( $boundaries ); ++$i ) {
			$start    = 0 === $i ? null : $boundaries[ $i - 1 ];
			$end      = isset( $boundaries[ $i ] ) ? self::shift_date( $boundaries[ $i ], -1 ) : null;
			$date     = $start ? $start : $end;
			$day      = self::starts_on( $schedule, $date );
			$next     = self::starts_on( $schedule, self::shift_date( $date, 1 ) );
			$previous = self::starts_on( $schedule, self::shift_date( $date, -1 ) );
			$carry    = false;
			foreach ( $previous['intervals'] as $interval ) {
				if ( $previous['exception'] && $interval[1] > 1440 ) {
					$carry = true;
				}
			}
			$clip_weekly = false;
			if ( $next['exception'] && $start && $start === $end ) {
				foreach ( $day['intervals'] as $interval ) {
					if ( $interval[1] > 1440 ) {
						$clip_weekly = true;
					}
				}
			}
			if ( ! $day['exception'] && ! $carry && ! $clip_weekly ) {
				continue;
			}
			$intervals = $day['exception'] ? $day['intervals'] : self::for_date( $schedule, $date );
			foreach ( $intervals ? $intervals : array( array( 0, 0 ) ) as $interval ) {
				if ( $next['exception'] && $interval[1] > 1440 ) {
					$interval[1] = 1440;
				}
				$record = array(
					'@type'  => 'OpeningHoursSpecification',
					'opens'  => self::clock( $interval[0] ),
					'closes' => self::schema_close( $interval ),
				);
				if ( $start ) {
					$record['validFrom'] = $start;
				}
				if ( $end ) {
					$record['validThrough'] = $end;
				}
				$merged = false;
				foreach ( $result as &$existing ) {
					if ( $start && isset( $existing['validThrough'] ) && self::shift_date( $existing['validThrough'], 1 ) === $start && $existing['opens'] === $record['opens'] && $existing['closes'] === $record['closes'] ) {
						if ( $end ) {
							$existing['validThrough'] = $end;
						} else {
							unset( $existing['validThrough'] );
						}
						$merged = true;
						break;
					}
				}
				unset( $existing );
				if ( ! $merged ) {
					$result[] = $record;
				}
			}
		}
		return $result;
	}
}
