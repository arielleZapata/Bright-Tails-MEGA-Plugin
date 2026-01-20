import React from "react";
import ReactDOM from "react-dom/client";

const divsToUpdate = document.querySelectorAll(".tailwind-update-me");

divsToUpdate.forEach((div) => {
  const data = JSON.parse(div.querySelector("pre").innerText);
  const root = ReactDOM.createRoot(div);
  root.render(<OurComponent {...data} />);
  div.classList.remove("tailwind-update-me");
});

function OurComponent(props) {
  // Calculate age from start date (auto-calculates over time)
  const calculateAge = (startDate) => {
    if (!startDate) return null;

    const start = new Date(startDate);
    const now = new Date();

    // Check if valid date
    if (isNaN(start.getTime())) return null;

    const diffTime = now - start;
    const diffMonths = Math.floor(
      (now.getFullYear() - start.getFullYear()) * 12 +
        (now.getMonth() - start.getMonth())
    );
    const diffYears = Math.floor(diffTime / (1000 * 60 * 60 * 24 * 365.25));

    // If less than 1 year, show in months
    if (diffMonths < 12) {
      return `${diffMonths} ${diffMonths === 1 ? "month" : "months"}`;
    } else {
      return `${diffYears} ${diffYears === 1 ? "year" : "years"}`;
    }
  };

  // Calculate age, but show "TBD" if age is "TBD" or invalid
  const ageDisplay =
    props.age && props.age !== "TBD" ? calculateAge(props.age) : null;
  const ageToShow = ageDisplay || (props.age === "TBD" ? "TBD" : null);

  return (
    <div className="my-unique-plugin-wrapper-class">
      <div className="bg-white rounded-2xl overflow-hidden shadow-sm flex flex-col md:flex-row max-w-[1024px] mx-auto my-4 h-auto md:h-[300px] md:min-h-[300px]">
        {/* Image on the left */}
        <div className="flex-shrink-0 w-full max-w-full h-[300px] md:w-64 md:h-full md:max-w-64 overflow-hidden">
          {props.imageUrl ? (
            <img
              src={props.imageUrl}
              alt={props.name || "Pet photo"}
              className="w-full h-full max-w-full max-h-full object-cover"
              style={{ maxWidth: "100%", maxHeight: "100%" }}
            />
          ) : (
            <div className="w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">
              No image
            </div>
          )}
        </div>

        {/* Pet info on the right */}
        <div
          className="flex-1 p-6 flex flex-col justify-center outfit"
          style={{ color: "#000000" }}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-10">
            {props.name && (
              <div className="flex flex-col justify-center items-center gap-2 md:gap-5">
                <strong
                  className="bowlby-one-sc-regular"
                  style={{ color: "#000000" }}
                >
                  NAME
                </strong>
                <span
                  className="outfit text-center"
                  style={{ color: "#000000" }}
                >
                  {props.name}
                </span>
              </div>
            )}
            {ageToShow && (
              <div className="flex flex-col justify-center items-center gap-2 md:gap-5">
                <strong
                  className="bowlby-one-sc-regular"
                  style={{ color: "#000000" }}
                >
                  AGE
                </strong>
                <span
                  className="outfit text-center"
                  style={{ color: "#000000" }}
                >
                  {ageToShow}
                </span>
              </div>
            )}
            <div className="flex flex-col justify-center items-center gap-2 md:gap-5">
              <strong
                className="bowlby-one-sc-regular"
                style={{ color: "#000000" }}
              >
                BREED
              </strong>
              <span className="outfit text-center" style={{ color: "#000000" }}>
                {props.breed || "TBD"}
              </span>
            </div>
            <div className="flex flex-col justify-center items-center gap-2 md:gap-5">
              <strong
                className="bowlby-one-sc-regular"
                style={{ color: "#000000" }}
              >
                WEIGHT
              </strong>
              <span className="outfit text-center" style={{ color: "#000000" }}>
                {props.weight || "TBD"}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
