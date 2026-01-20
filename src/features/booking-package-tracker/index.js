import {
	useBlockProps,
	InspectorControls
} from "@wordpress/block-editor";
import { PanelBody, TextControl } from "@wordpress/components";
import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";

registerBlockType(metadata.name, { edit: EditComponent });

function EditComponent(props) {
	function updateEmail(e) {
		props.setAttributes({ email: e.target.value });
	}

	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<PanelBody title="Booking Package Tracker Settings">
					<TextControl
						label="Customer Email"
						value={props.attributes.email || ""}
						onChange={updateEmail}
						placeholder="customer@example.com"
						help="Enter the customer email to track their booking package credits"
					/>
				</PanelBody>
			</InspectorControls>
			<div className="bt-booking-tracker-preview">
				<div style={{
					padding: '20px',
					border: '2px dashed #ccc',
					borderRadius: '4px',
					backgroundColor: '#f9f9f9'
				}}>
					<h3 style={{ marginTop: 0 }}>Booking Package Tracker</h3>
					{props.attributes.email ? (
						<p style={{ margin: '10px 0', color: '#666' }}>
							Tracking packages for: <strong>{props.attributes.email}</strong>
						</p>
					) : (
						<p style={{ margin: '10px 0', color: '#856404', padding: '10px', background: '#fff3cd', borderRadius: '4px' }}>
							⚠️ Please enter a customer email in the block settings panel (right sidebar) to track their booking packages.
						</p>
					)}
					<p style={{ fontSize: '12px', color: '#999', fontStyle: 'italic', marginTop: '10px' }}>
						Preview: The actual tracker display will show on the frontend
					</p>
				</div>
			</div>
		</div>
	);
}
