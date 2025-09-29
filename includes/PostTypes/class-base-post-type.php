<?php
namespace VRSP\PostTypes;

/**
 * Shared helpers for custom post types.
 */
abstract class BasePostType {
/**
 * Post type key.
 */
abstract public static function get_key(): string;

/**
 * Registers the post type.
 */
abstract public static function register(): void;

/**
 * Flush rewrite rules on demand.
 */
public static function flush_rewrite(): void {
flush_rewrite_rules();
}
}
