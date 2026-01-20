import "./style.css";

import {
  useBlockProps,
  MediaUpload,
  MediaUploadCheck
} from "@wordpress/block-editor";
import { Button } from "@wordpress/components";
import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";

registerBlockType(metadata.name, { edit: EditComponent });

function EditComponent(props) {
  function updateName(e) {
    props.setAttributes({ name: e.target.value });
  }

  function updateAge(e) {
    props.setAttributes({ age: e.target.value });
  }

  function updateBreed(e) {
    props.setAttributes({ breed: e.target.value });
  }

  function updateWeight(e) {
    props.setAttributes({ weight: e.target.value });
  }

  function onSelectImage(media) {
    if (media && media.url) {
      props.setAttributes({
        imageId: media.id,
        imageUrl: media.url
      });
    }
  }

  function removeImage() {
    props.setAttributes({
      imageId: null,
      imageUrl: ""
    });
  }

  function updateOwnerEmail(e) {
    props.setAttributes({ ownerEmail: e.target.value });
  }

  return (
    <div {...useBlockProps()}>
      <div className="my-unique-plugin-wrapper-class">
        <div className="bg-blue-200 border-2 border-blue-300 rounded-md p-5 space-y-3">
          <div>
            <label className="block mb-1 font-semibold">Pet Name:</label>
            <input
              className="w-full p-2 rounded-lg"
              type="text"
              value={props.attributes.name || ""}
              onChange={updateName}
              placeholder="Enter pet name..."
            />
          </div>
          <div>
            <label className="block mb-1 font-semibold">
              Age (Start Date):
            </label>
            <input
              className="w-full p-2 rounded-lg"
              type="date"
              value={props.attributes.age || ""}
              onChange={updateAge}
            />
            <p className="text-xs text-gray-600 mt-1">
              Set the start date (age will auto-calculate over time)
            </p>
          </div>
          <div>
            <label className="block mb-1 font-semibold">Breed:</label>
            <input
              className="w-full p-2 rounded-lg"
              type="text"
              value={props.attributes.breed || ""}
              onChange={updateBreed}
              placeholder="e.g., Golden Doodle"
            />
          </div>
          <div>
            <label className="block mb-1 font-semibold">Weight:</label>
            <input
              className="w-full p-2 rounded-lg"
              type="text"
              value={props.attributes.weight || ""}
              onChange={updateWeight}
              placeholder="e.g., TBD or 25 lbs"
            />
          </div>
          <div>
            <label className="block mb-1 font-semibold">Pet Image:</label>
            <MediaUploadCheck>
              <MediaUpload
                onSelect={onSelectImage}
                allowedTypes={["image"]}
                value={props.attributes.imageId}
                render={({ open }) => (
                  <div>
                    {props.attributes.imageUrl ? (
                      <div className="mb-2">
                        <img
                          src={props.attributes.imageUrl}
                          alt={props.attributes.name || "Pet"}
                          className="max-w-full h-auto max-h-48 object-cover rounded"
                        />
                        <div className="mt-2 space-x-2">
                          <Button onClick={open} variant="secondary" isSmall>
                            Change Image
                          </Button>
                          <Button
                            onClick={removeImage}
                            variant="secondary"
                            isSmall
                            isDestructive
                          >
                            Remove Image
                          </Button>
                        </div>
                      </div>
                    ) : (
                      <Button onClick={open} variant="primary" isSecondary>
                        Upload Image
                      </Button>
                    )}
                  </div>
                )}
              />
            </MediaUploadCheck>
          </div>
          <div>
            <label className="block mb-1 font-semibold">Owner Email:</label>
            <input
              className="w-full p-2 rounded-lg"
              type="email"
              value={props.attributes.ownerEmail || ""}
              onChange={updateOwnerEmail}
              placeholder="owner@email.com"
            />
            <p className="text-xs text-gray-600 mt-1">
              Used to match form submissions
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
