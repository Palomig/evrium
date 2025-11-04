import { useEffect, useRef } from 'react';

const InteractiveFigure = ({ id, initBoard }) => {
  const boardRef = useRef(null);

  useEffect(() => {
    if (boardRef.current && typeof window !== 'undefined') {
      // Очистка предыдущего борда
      if (window.JXG && window.JXG.JSXGraph.boards[id]) {
        window.JXG.JSXGraph.freeBoard(window.JXG.JSXGraph.boards[id]);
      }

      // Инициализация нового борда
      if (initBoard) {
        const board = window.JXG.JSXGraph.initBoard(id, {
          boundingbox: [-5, 5, 5, -5],
          axis: true,
          showCopyright: false,
          showNavigation: false,
          zoom: {
            factorX: 1.25,
            factorY: 1.25,
            wheel: true,
          },
          pan: {
            enabled: true,
          },
        });

        initBoard(board);
      }
    }

    return () => {
      if (window.JXG && window.JXG.JSXGraph.boards[id]) {
        window.JXG.JSXGraph.freeBoard(window.JXG.JSXGraph.boards[id]);
      }
    };
  }, [id, initBoard]);

  return (
    <div className="w-full">
      <div
        id={id}
        ref={boardRef}
        className="w-full h-64 md:h-80 lg:h-96 bg-white rounded-lg border border-gray-200"
        style={{ touchAction: 'none' }}
      />
    </div>
  );
};

export default InteractiveFigure;
