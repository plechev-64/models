<?php

class ModelMeta extends Model {

	static $singleMetaKeys = [];
	static $multiMetaKeys = [];

	static $tableKeys = [
		'post_id'    => 0,
		'meta_value' => '',
		'meta_key'   => ''
	];

	static $originKeys = [
		'mid'     => 'mid',
		'item_id' => 'post_id',
		'value'   => 'meta_value',
		'key'     => 'meta_key'
	];

	static function setupMetaData( $metadata ) {

		$setupMeta = [];

		foreach ( $metadata as $data ) {

			if ( isset( static::$singleMetaKeys[ $data->meta_key ] ) ) {

				$setupMeta[ $data->meta_key ] = maybe_unserialize( $data->meta_value );

			} else if ( isset( static::$multiMetaKeys[ $data->meta_key ] ) ) {

				$setupMeta[ $data->meta_key ][] = $data->meta_value;

			}
		}

		return $setupMeta;

	}

	static function add( $item_id, $key, $value ) {
		return static::insertData( [
			static::$originKeys['item_id'] => $item_id,
			static::$originKeys['key']     => $key,
			static::$originKeys['value']   => maybe_serialize( $value )
		] );
	}

	static function get( $item_id, $key, $all = false ) {

		if ( $all ) {

			$results = static::query()->select( [ static::$originKeys['value'] ] )->where( [
				static::$originKeys['item_id'] => $item_id,
				static::$originKeys['key']     => $key
			] )->limit( - 1 )->get_col();

			if ( $results ) {
				foreach ( $results as $k => $r ) {
					$results[ $k ] = maybe_unserialize( $r );
				}
			}

			return $results;

		}

		return maybe_unserialize( static::query()->select( [ static::$originKeys['value'] ] )->where( [
			static::$originKeys['item_id'] => $item_id,
			static::$originKeys['key']     => $key
		] )->get_var() );
	}

	static function update( $item_id, $key, $value ) {
		global $wpdb;

		$where = [
			static::$originKeys['item_id'] => $item_id,
			static::$originKeys['key']     => $key
		];

		$update = [
			static::$originKeys['value'] => maybe_serialize( $value )
		];

		if ( ! static::query()->where( $where )->get_var() ) {
			return static::insertData( array_merge( $where, $update ) );
		}

		$update = static::setDefaults( $update, true );

		return $wpdb->update(
			$wpdb->prefix . static::$tableName, $update, $where
		);

	}

	static function deleteValuesByKey( $item_id, $key ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . static::$tableName . " WHERE " . static::$originKeys['item_id'] . "='$item_id' AND " . static::$originKeys['key'] . "='$key'" );
	}

}