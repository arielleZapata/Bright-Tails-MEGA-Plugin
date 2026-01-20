import React, { useState, useEffect } from "react";
import ReactDOM from "react-dom/client";

// Debug: Verify script is loading
console.log("Booking Tracker: Script loaded");
window.brighttailsBookingTrackerLoaded = true;

const divsToUpdate = document.querySelectorAll(".bt-booking-tracker-update-me");

divsToUpdate.forEach((div) => {
	try {
		const preElement = div.querySelector("pre");
		if (!preElement) {
			console.warn("Booking Tracker: No pre element found in", div);
			return;
		}
		
		const jsonText = preElement.innerText;
		if (!jsonText) {
			console.warn("Booking Tracker: Empty JSON data");
			return;
		}
		
		const data = JSON.parse(jsonText);
		const root = ReactDOM.createRoot(div);
		root.render(<BookingTrackerComponent {...data} />);
		div.classList.remove("bt-booking-tracker-update-me");
		console.log("Booking Tracker: Component rendered for", data.email);
	} catch (error) {
		console.error("Booking Tracker: Error initializing component", error, div);
	}
});

function BookingTrackerComponent(props) {
	const { email, credits, hasPackage, calEmail } = props;
	const [lastPayment, setLastPayment] = useState(null);
	const [paymentLoading, setPaymentLoading] = useState(true);
	const [paymentError, setPaymentError] = useState(null);

	useEffect(() => {
		if (!email) {
			setPaymentLoading(false);
			return;
		}

		// Fetch last payment from REST API
		const fetchLastPayment = async () => {
			try {
				setPaymentLoading(true);
				setPaymentError(null);
				
				// Build API URL with both Stripe email and Cal.com email (if provided)
				let apiUrl = `/wp-json/brighttails/v1/me/last-payment?email=${encodeURIComponent(email)}`;
				if (calEmail && calEmail !== email) {
					apiUrl += `&cal_email=${encodeURIComponent(calEmail)}`;
				}
				
				console.log('BT Last Payment: Fetching with Stripe email:', email, 'Cal.com email:', calEmail || email);
				
				const response = await fetch(apiUrl);
				const data = await response.json();
				
				// Debug: Log response to console
				console.log('BT Last Payment API Response:', data);
				
				if (response.ok) {
					if (data.found) {
						setLastPayment(data);
						// Log debug info
						if (data.debug) {
							console.log('BT Debug Info:', {
								'Stripe Email': data.debug.stripe_email,
								'Cal.com Email': data.debug.cal_email,
								'Customer ID': data.debug.customer_id,
								'Customer Name': data.debug.customer_name,
								'Cal.com API Queried': data.debug.cal_api_queried,
								'Cal.com API Key Configured': data.debug.cal_api_key_configured,
								'Cal.com API Error': data.debug.cal_api_error,
								'Cal.com API Response Code': data.debug.cal_api_response_code,
								'Cal.com Bookings Count': data.cal_bookings_count
							});
						}
					} else {
						// Not an error - just no payment found, but still set data for invoices
						setLastPayment(data);
						if (data.debug) {
							console.log('BT Debug Info (no payment):', data.debug);
						}
					}
				} else {
					setLastPayment(null);
				}
			} catch (error) {
				console.error('Booking Tracker: Error fetching last payment', error);
				setPaymentError(error.message);
				setLastPayment(null);
			} finally {
				setPaymentLoading(false);
			}
		};

		fetchLastPayment();
	}, [email, calEmail]);

	// Format date for display
	const formatPaymentDate = (isoString) => {
		if (!isoString) return '';
		try {
			const date = new Date(isoString);
			return date.toLocaleDateString('en-US', {
				year: 'numeric',
				month: 'short',
				day: 'numeric'
			});
		} catch (e) {
			return isoString;
		}
	};

	// Format date with time for invoices
	const formatInvoiceDate = (isoString) => {
		if (!isoString) return '';
		try {
			const date = new Date(isoString);
			return date.toLocaleDateString('en-US', {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			});
		} catch (e) {
			return isoString;
		}
	};

	return (
		<div className="my-unique-plugin-wrapper-class">
			<div className="bg-white rounded-2xl overflow-hidden shadow-sm flex flex-col max-w-[1024px] mx-auto my-4 p-6">
				{!email ? (
					<div className="text-center py-8">
						<p className="outfit text-red-600">
							Error: No email address provided. Please configure the block settings.
						</p>
					</div>
				) : !hasPackage ? (
					<div className="text-center py-8">
						<h2
							className="bowlby-one-sc-regular text-2xl mb-4"
							style={{ color: "#000000" }}
						>
							WAITING ON PURCHASE
						</h2>
						<p className="outfit" style={{ color: "#000000" }}>
							No booking package has been purchased yet. Purchase a package to start booking appointments.
						</p>
					</div>
				) : (
					<div className="flex flex-col items-center justify-center py-8">
						<h2
							className="bowlby-one-sc-regular text-2xl mb-6"
							style={{ color: "#000000" }}
						>
							BOOKING PACKAGE
						</h2>
						<div className="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-10 w-full max-w-md">
							<div className="flex flex-col justify-center items-center gap-2 md:gap-5">
								<strong
									className="bowlby-one-sc-regular text-center whitespace-nowrap"
									style={{ color: "#000000", minWidth: '100%' }}
								>
									REMAINING CREDITS
								</strong>
								<span
									className="outfit text-center text-3xl font-bold"
									style={{ color: "#000000" }}
								>
									{credits}
								</span>
							</div>
							<div className="flex flex-col justify-center items-center gap-2 md:gap-5">
								<strong
									className="bowlby-one-sc-regular text-center"
									style={{ color: "#000000" }}
								>
									STATUS
								</strong>
								<span
									className="outfit text-center"
									style={{ color: "#000000" }}
								>
									Active
								</span>
							</div>
						</div>
					</div>
				)}
			</div>
		</div>
	);
}
