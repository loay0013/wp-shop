jQuery(document).ready(function ($) {
  // Fetch products
  function fetchProducts() {
    $.getJSON("http://localhost:8888/web-wp/wp-json/cpd/v1/products", function (products) {
      var tbody = $("#cpd-products-table tbody");
      tbody.empty();

      if (products.length === 0) {
        tbody.append('<tr><td colspan="6">No products found.</td></tr>');
        return;
      }

      products.forEach(function (product) {
        var row = $("<tr></tr>");
        row.append("<td>" + product.id + "</td>");
        row.append("<td>" + product.name + "</td>");
        row.append(
          '<td><img src="' + product.image + '" width="50" height="50" /></td>'
        );
        row.append("<td>" + product.description + "</td>");
        row.append("<td>$" + product.price.toFixed(2) + "</td>");

        var actions = $("<td></td>");
        actions.append(
          '<button class="button edit-product" data-id="' +
            product.id +
            '">Edit</button> '
        );
        actions.append(
          '<button class="button delete-product" data-id="' +
            product.id +
            '">Delete</button>'
        );
        row.append(actions);

        tbody.append(row);
      });
    });
  }

  fetchProducts();

  // Edit product
  $(document).on("click", ".edit-product", function () {
    var id = $(this).data("id");
    // Implement the edit functionality here
  });

  // Delete product
  $(document).on("click", ".delete-product", function () {
    var id = $(this).data("id");
    var confirmDelete = confirm(
      "Are you sure you want to delete the product with ID: " + id + "?"
    );

    if (!confirmDelete) {
      return;
    }

    $.ajax({
      method: "POST",
      url: "http://localhost:8888/web-wp/wp-json/cpd/v1/products",
      data: {
        action: "cpd_delete_product",
        nonce: cpd_ajax.nonce,
        id: id,
      },
      success: function (response) {
        if (response.success) {
          alert("Product deleted successfully.");
          fetchProducts();
        } else {
          alert("Error deleting the product.");
        }
      },
    });
  });

  // Purge all data
  $("#cpd-purge-data").on("click", function () {
    var confirmPurge = confirm(
      "Are you sure you want to purge all product data?"
    );

    if (!confirmPurge) {
      return;
    }

    $.ajax({
      method: "POST",
      url: "http://localhost:8888/web-wp/wp-json/cpd/v1/products",
      data: {
        action: "cpd_purge_data",
        nonce: cpd_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          alert("All product data purged successfully.");
          fetchProducts();
        } else {
          alert("Error purging product data.");
        }
      },
    });
  });
});
