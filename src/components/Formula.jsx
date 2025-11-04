import { useEffect, useRef } from 'react';

const Formula = ({ latex, display = false }) => {
  const formulaRef = useRef(null);

  useEffect(() => {
    if (formulaRef.current && window.MathJax) {
      window.MathJax.typesetPromise([formulaRef.current]).catch((err) =>
        console.error('MathJax error:', err)
      );
    }
  }, [latex]);

  return (
    <span
      ref={formulaRef}
      className={display ? 'block my-4 text-center' : 'inline'}
    >
      {display ? `\\[${latex}\\]` : `\\(${latex}\\)`}
    </span>
  );
};

export default Formula;
