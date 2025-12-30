<?php
/**
 * Bottom Sheet Template.
 *
 * @package BookingX\MobileOptimize
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Bottom Sheet Container -->
<div id="bkx-bottom-sheet" class="bkx-bottom-sheet" style="display: none;">
	<div class="bkx-bottom-sheet-backdrop"></div>
	<div class="bkx-bottom-sheet-container">
		<div class="bkx-bottom-sheet-handle">
			<span></span>
		</div>
		<div class="bkx-bottom-sheet-header">
			<h3 class="bkx-bottom-sheet-title"></h3>
			<button type="button" class="bkx-bottom-sheet-close" aria-label="<?php esc_attr_e( 'Close', 'bkx-mobile-optimize' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<div class="bkx-bottom-sheet-content"></div>
		<div class="bkx-bottom-sheet-footer"></div>
	</div>
</div>

<style>
.bkx-bottom-sheet {
	position: fixed;
	inset: 0;
	z-index: 99999;
}

.bkx-bottom-sheet-backdrop {
	position: absolute;
	inset: 0;
	background: rgba(0, 0, 0, 0.5);
	opacity: 0;
	transition: opacity 0.3s ease-out;
}

.bkx-bottom-sheet.visible .bkx-bottom-sheet-backdrop {
	opacity: 1;
}

.bkx-bottom-sheet-container {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	background: #fff;
	border-radius: 16px 16px 0 0;
	max-height: 90vh;
	display: flex;
	flex-direction: column;
	transform: translateY(100%);
	transition: transform 0.3s ease-out;
	will-change: transform;
}

.bkx-bottom-sheet.visible .bkx-bottom-sheet-container {
	transform: translateY(0);
}

.bkx-bottom-sheet-handle {
	display: flex;
	justify-content: center;
	padding: 12px;
	cursor: grab;
}

.bkx-bottom-sheet-handle span {
	width: 36px;
	height: 4px;
	background: #e5e7eb;
	border-radius: 2px;
}

.bkx-bottom-sheet-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 16px 16px;
	border-bottom: 1px solid #f3f4f6;
}

.bkx-bottom-sheet-title {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: #1f2937;
}

.bkx-bottom-sheet-close {
	background: none;
	border: none;
	padding: 8px;
	cursor: pointer;
	color: #9ca3af;
	border-radius: 8px;
	transition: background 0.2s;
}

.bkx-bottom-sheet-close:hover {
	background: #f3f4f6;
}

.bkx-bottom-sheet-content {
	flex: 1;
	overflow-y: auto;
	-webkit-overflow-scrolling: touch;
	padding: 16px;
}

.bkx-bottom-sheet-footer {
	padding: 16px;
	border-top: 1px solid #f3f4f6;
}

.bkx-bottom-sheet-footer:empty {
	display: none;
}

/* Options list for selection sheets */
.bkx-sheet-options {
	list-style: none;
	margin: 0;
	padding: 0;
}

.bkx-sheet-option {
	display: flex;
	align-items: center;
	padding: 16px;
	border-radius: 12px;
	cursor: pointer;
	transition: background 0.2s;
}

.bkx-sheet-option:hover {
	background: #f9fafb;
}

.bkx-sheet-option:active {
	background: #f3f4f6;
}

.bkx-sheet-option.selected {
	background: #eff6ff;
}

.bkx-sheet-option.selected::after {
	content: '';
	margin-left: auto;
	width: 24px;
	height: 24px;
	background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%232563eb' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M5 13l4 4L19 7'/%3E%3C/svg%3E") no-repeat center;
}

.bkx-sheet-option-icon {
	width: 40px;
	height: 40px;
	background: #f3f4f6;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 12px;
}

.bkx-sheet-option-text {
	flex: 1;
}

.bkx-sheet-option-title {
	font-weight: 500;
	color: #1f2937;
}

.bkx-sheet-option-subtitle {
	font-size: 13px;
	color: #6b7280;
	margin-top: 2px;
}

/* Time picker sheet */
.bkx-time-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 8px;
}

.bkx-time-slot {
	padding: 12px;
	text-align: center;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.2s;
}

.bkx-time-slot:hover {
	border-color: #2563eb;
}

.bkx-time-slot.selected {
	background: #2563eb;
	color: #fff;
	border-color: #2563eb;
}

.bkx-time-slot.unavailable {
	opacity: 0.5;
	cursor: not-allowed;
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
	.bkx-bottom-sheet-container {
		background: #1f2937;
	}

	.bkx-bottom-sheet-title {
		color: #f9fafb;
	}

	.bkx-bottom-sheet-header,
	.bkx-bottom-sheet-footer {
		border-color: #374151;
	}

	.bkx-sheet-option:hover {
		background: #374151;
	}

	.bkx-sheet-option-title {
		color: #f9fafb;
	}

	.bkx-sheet-option-icon {
		background: #374151;
	}
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
	.bkx-bottom-sheet-backdrop,
	.bkx-bottom-sheet-container {
		transition: none;
	}
}
</style>
