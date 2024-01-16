$(function () {
    var container = $('.canvas-container');
    var chart= $('#categories-chart');
    ctx.attr('width', container.width());
    ctx.attr('height', 300);
});
$(function () {
    var container = $('.canvas-container');
    var chart= $('#locations-chart');
    ctx.attr('width', container.width());
    ctx.attr('height', 300);
});
$(function () {
    var container = $('.canvas-container');
    var chart= $('#monthly-chart');
    ctx.attr('width', container.width());
    ctx.attr('height', 300);
});


var categoriesChart = new Chart(document.getElementById('locations-chart'), {
    type: 'pie',
    data: categoriesData,
    options: {
        responsive: true, // グラフが親要素に合わせて変化する
        maintainAspectRatio: false, // アスペクト比を保持しない
    },
});
var categoriesData = {
    labels: <?php echo json_encode($categories_labels); ?>,
    datasets: [{
        data: <?php echo json_encode($categories_values); ?>,
        backgroundColor: ['red', 'blue', 'green', 'yellow', 'orange'],
        borderColor: ['white', 'white', 'white', 'white', 'white'],
        borderWidth: 2,
    }]
};
